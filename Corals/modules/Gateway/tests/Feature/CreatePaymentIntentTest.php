<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use Laravel\Sanctum\Sanctum;

class CreatePaymentIntentTest extends GatewayTestCase
{
    private function payload(string $mid, string $invoiceId = 'INV-1'): array
    {
        return [
            'invoice_id' => $invoiceId,
            'mid' => $mid,
            'mode' => 'one_time',
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
        ];
    }

    public function test_happy_path_creates_an_active_intent_with_a_reference(): void
    {
        $issuer = Issuer::factory()->create();
        $merchant = Merchant::factory()->create(['issuer_id' => $issuer->id]);
        Sanctum::actingAs($issuer, ['*']);

        $response = $this->postJson('/api/v1/payment-intents', $this->payload($merchant->mid), [
            'Idempotency-Key' => 'key-1',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('invoice_id', 'INV-1');
        $response->assertJsonPath('state', 'ACTIVE');
        $response->assertJsonCount(1, 'references');
        $this->assertNotEmpty($response->json('references.0.human_reference'));
        $this->assertSame(1, PaymentIntent::count());
    }

    public function test_idempotent_replay_returns_the_stored_response_with_no_second_effect(): void
    {
        $issuer = Issuer::factory()->create();
        $merchant = Merchant::factory()->create(['issuer_id' => $issuer->id]);
        Sanctum::actingAs($issuer, ['*']);

        $first = $this->postJson('/api/v1/payment-intents', $this->payload($merchant->mid), [
            'Idempotency-Key' => 'key-replay',
        ]);
        $second = $this->postJson('/api/v1/payment-intents', $this->payload($merchant->mid), [
            'Idempotency-Key' => 'key-replay',
        ]);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame($first->json('public_id'), $second->json('public_id'));
        $this->assertSame(1, PaymentIntent::count());
    }

    public function test_reused_idempotency_key_with_a_different_body_is_rejected(): void
    {
        $issuer = Issuer::factory()->create();
        $merchant = Merchant::factory()->create(['issuer_id' => $issuer->id]);
        Sanctum::actingAs($issuer, ['*']);

        $this->postJson('/api/v1/payment-intents', $this->payload($merchant->mid, 'INV-1'), [
            'Idempotency-Key' => 'key-conflict',
        ])->assertStatus(201);

        $response = $this->postJson('/api/v1/payment-intents', $this->payload($merchant->mid, 'INV-2'), [
            'Idempotency-Key' => 'key-conflict',
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, PaymentIntent::count());
    }

    public function test_validation_failure_returns_422(): void
    {
        $issuer = Issuer::factory()->create();
        Sanctum::actingAs($issuer, ['*']);

        $response = $this->postJson('/api/v1/payment-intents', [
            'mode' => 'one_time',
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000],
        ], ['Idempotency-Key' => 'key-invalid']);

        $response->assertStatus(422);
        $this->assertSame(0, PaymentIntent::count());
    }

    public function test_missing_idempotency_key_header_returns_422(): void
    {
        $issuer = Issuer::factory()->create();
        $merchant = Merchant::factory()->create(['issuer_id' => $issuer->id]);
        Sanctum::actingAs($issuer, ['*']);

        $response = $this->postJson('/api/v1/payment-intents', $this->payload($merchant->mid));

        $response->assertStatus(422);
    }

    public function test_mid_belonging_to_another_issuer_is_rejected_as_forbidden(): void
    {
        $issuer = Issuer::factory()->create();
        $otherIssuer = Issuer::factory()->create();
        $otherMerchant = Merchant::factory()->create(['issuer_id' => $otherIssuer->id]);
        Sanctum::actingAs($issuer, ['*']);

        $response = $this->postJson('/api/v1/payment-intents', $this->payload($otherMerchant->mid), [
            'Idempotency-Key' => 'key-authz',
        ]);

        $response->assertStatus(403);
        $this->assertSame(0, PaymentIntent::count());
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $merchant = Merchant::factory()->create();

        $response = $this->postJson('/api/v1/payment-intents', $this->payload($merchant->mid), [
            'Idempotency-Key' => 'key-unauth',
        ]);

        $response->assertStatus(401);
    }
}
