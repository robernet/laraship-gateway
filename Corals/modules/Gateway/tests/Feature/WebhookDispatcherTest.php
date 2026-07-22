<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\Webhooks\WebhookDispatcher;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\WebhookDelivery;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use Illuminate\Support\Facades\Http;

class WebhookDispatcherTest extends GatewayTestCase
{
    public function test_delivers_a_signed_envelope_a_receiver_can_verify(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $issuer = Issuer::factory()->create();

        $delivery = (new WebhookDispatcher())->notify('payment.confirmed', [
            'issuer_id' => $issuer->id,
            'transaction_public_id' => 'txn_abc123',
            'amount' => 5000,
        ]);

        $this->assertSame('delivered', $delivery->status);
        $this->assertNotNull($delivery->delivered_at);

        Http::assertSent(function ($request) use ($issuer, $delivery) {
            return $request->url() === $issuer->webhook_url
                && $request['contract_v'] === 1
                && $request['event'] === 'payment.confirmed'
                && $request['signature'] === $delivery->payload['signature'];
        });

        // Receiver-side verification: recompute the HMAC over every envelope
        // field except `signature` with the shared webhook_secret.
        $envelope = $delivery->payload;
        $signature = $envelope['signature'];
        unset($envelope['signature']);

        $expected = hash_hmac('sha256', json_encode($envelope, JSON_UNESCAPED_SLASHES), $issuer->webhook_secret);

        $this->assertTrue(hash_equals($expected, $signature));
    }

    public function test_skips_issuers_without_a_webhook_url(): void
    {
        Http::fake();

        $issuer = Issuer::factory()->create(['webhook_url' => null]);

        $delivery = (new WebhookDispatcher())->notify('payment.confirmed', ['issuer_id' => $issuer->id]);

        $this->assertNull($delivery);
        Http::assertNothingSent();
        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_retries_reuse_the_original_nonce_so_a_receiver_can_detect_replay(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $issuer = Issuer::factory()->create();
        $dispatcher = new WebhookDispatcher();

        $delivery = $dispatcher->notify('payment.confirmed', ['issuer_id' => $issuer->id]);
        $nonceAfterFirstAttempt = $delivery->fresh()->payload['nonce'];

        $this->travelTo(now()->addMinutes(10));
        $dispatcher->retryPending();

        $nonceAfterRetry = $delivery->fresh()->payload['nonce'];

        // Same nonce on every retry: a receiver who has already seen it once
        // (from the first attempt reaching them, even if our HTTP client
        // never saw the ack) recognizes the retry as a duplicate rather than
        // a new event — the same mechanism that flags a genuine replay.
        $this->assertSame($nonceAfterFirstAttempt, $nonceAfterRetry);

        $otherDelivery = $dispatcher->notify('payment.confirmed', ['issuer_id' => $issuer->id]);
        $this->assertNotSame($nonceAfterFirstAttempt, $otherDelivery->payload['nonce']);
    }

    public function test_failed_delivery_is_left_pending_with_backoff_until_retried(): void
    {
        Http::fake(['*' => Http::sequence()->push('', 500)->push('', 200)]);

        $issuer = Issuer::factory()->create();
        $delivery = (new WebhookDispatcher())->notify('payment.confirmed', ['issuer_id' => $issuer->id]);

        $this->assertSame('pending', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame('HTTP 500', $delivery->last_error);
        $this->assertTrue($delivery->next_retry_at->isFuture());

        // Not due yet: a sweep right now must not touch it.
        $processed = (new WebhookDispatcher())->retryPending();
        $this->assertSame(0, $processed);
        $this->assertSame(1, $delivery->fresh()->attempts);

        $this->travelTo($delivery->next_retry_at->copy()->addSecond());

        $processed = (new WebhookDispatcher())->retryPending();
        $this->assertSame(1, $processed);
        $this->assertSame('delivered', $delivery->fresh()->status);
    }

    public function test_delivery_is_marked_failed_after_max_attempts_are_exhausted(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $issuer = Issuer::factory()->create();
        $dispatcher = new WebhookDispatcher();
        $delivery = $dispatcher->notify('payment.confirmed', ['issuer_id' => $issuer->id]);

        // First attempt already happened inside notify(); exhaust the rest.
        for ($i = 0; $i < 7; $i++) {
            $dispatcher->attempt($delivery->fresh(), $issuer);
        }

        $delivery = $delivery->fresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(8, $delivery->attempts);
        $this->assertNull($delivery->next_retry_at);
    }
}
