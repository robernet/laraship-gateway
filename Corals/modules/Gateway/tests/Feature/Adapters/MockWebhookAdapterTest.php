<?php

namespace Tests\Feature\Adapters;

use Corals\Modules\Gateway\Adapters\MockWebhook\MockWebhookAdapter;
use Corals\Modules\Gateway\Adapters\MockWebhook\MockWebhookReplayException;
use Corals\Modules\Gateway\Adapters\MockWebhook\MockWebhookSignatureException;
use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\Models\NetworkAdapter;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\Models\PaymentReference;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\Transaction;
use Corals\Modules\Gateway\tests\GatewayTestCase;

class MockWebhookAdapterTest extends GatewayTestCase
{
    private const SECRET = 'test-webhook-secret';

    private function setUpFixture(): array
    {
        NetworkAdapter::factory()->create([
            'network_id' => 'mock-webhook',
            'archetype' => 'webhook',
            'config' => ['shared_secret' => self::SECRET],
        ]);

        $merchant = Merchant::factory()->create();
        $intent = PaymentIntent::factory()->create([
            'merchant_id' => $merchant->id,
            'state' => 'ACTIVE',
            'mode' => 'one_time',
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
            'overpay_policy' => 'reject',
            'underpay_policy' => 'reject',
        ]);
        $reference = PaymentReference::factory()->create([
            'payment_intent_id' => $intent->id,
            'status' => 'active',
            'expires_at' => now()->addHour(),
        ]);
        $wallet = PosWallet::factory()->create([
            'network_id' => 'mock-webhook',
            'external_store_id' => 'S-001',
            'balance_centavos' => 50000,
            'reserved_centavos' => 0,
        ]);

        return [$merchant, $reference, $wallet];
    }

    private function confirmPayload(Merchant $merchant, PaymentReference $reference, array $overrides = []): array
    {
        return array_merge([
            'mid' => $merchant->mid,
            'ref' => $reference->reference_token,
            'amount_paid' => 10000,
            'is_partial' => false,
            'txn_id' => 'NTX-'.uniqid(),
            'idempotency_key' => 'IDEMP-'.uniqid(),
            'store_id' => 'S-001',
            'terminal_id' => 'T-01',
            'collected_at' => now()->getTimestamp(),
        ], $overrides);
    }

    private function sign(array $payload, string $secret = self::SECRET): string
    {
        $fields = $payload;
        unset($fields['signature']);
        ksort($fields);

        return hash_hmac('sha256', json_encode($fields), $secret);
    }

    public function test_signed_push_confirms_through_the_contract(): void
    {
        [$merchant, $reference, $wallet] = $this->setUpFixture();
        $adapter = new MockWebhookAdapter(sharedSecret: self::SECRET);
        $payload = $this->confirmPayload($merchant, $reference);
        $payload['signature'] = $this->sign($payload);

        $result = $adapter->receive($payload);

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['transaction_public_id']);
        $this->assertSame(10000, $result['receipt']['amount']);
        $this->assertSame('CONFIRMED', Transaction::first()->state);
        $this->assertSame(40000, $wallet->fresh()->balance_centavos);
    }

    public function test_unsigned_push_is_rejected_before_touching_core(): void
    {
        [$merchant, $reference] = $this->setUpFixture();
        $adapter = new MockWebhookAdapter(sharedSecret: self::SECRET);
        $payload = $this->confirmPayload($merchant, $reference);

        $this->expectException(MockWebhookSignatureException::class);

        try {
            $adapter->receive($payload);
        } finally {
            $this->assertSame(0, Transaction::count());
        }
    }

    public function test_tampered_signature_is_rejected_before_touching_core(): void
    {
        [$merchant, $reference] = $this->setUpFixture();
        $adapter = new MockWebhookAdapter(sharedSecret: self::SECRET);
        $payload = $this->confirmPayload($merchant, $reference);
        $payload['signature'] = $this->sign($payload);
        $payload['amount_paid'] = 99999;

        $this->expectException(MockWebhookSignatureException::class);

        try {
            $adapter->receive($payload);
        } finally {
            $this->assertSame(0, Transaction::count());
        }
    }

    public function test_replayed_push_is_rejected_not_reprocessed(): void
    {
        [$merchant, $reference] = $this->setUpFixture();
        $adapter = new MockWebhookAdapter(sharedSecret: self::SECRET);
        $payload = $this->confirmPayload($merchant, $reference);
        $payload['signature'] = $this->sign($payload);

        $first = $adapter->receive($payload);

        $this->assertTrue($first['ok']);
        $this->expectException(MockWebhookReplayException::class);

        try {
            $adapter->receive($payload);
        } finally {
            $this->assertSame(1, Transaction::where('state', 'CONFIRMED')->count());
        }
    }
}
