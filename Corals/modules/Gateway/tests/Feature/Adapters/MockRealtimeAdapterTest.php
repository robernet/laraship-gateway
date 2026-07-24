<?php

namespace Tests\Feature\Adapters;

use Corals\Modules\Gateway\Adapters\MockRealtime\MockRealtimeAdapter;
use Corals\Modules\Gateway\Adapters\MockRealtime\MockRealtimeTimeoutException;
use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\Models\NetworkAdapter;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\Models\PaymentReference;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\Transaction;
use Corals\Modules\Gateway\tests\GatewayTestCase;

class MockRealtimeAdapterTest extends GatewayTestCase
{
    private function setUpFixture(): array
    {
        NetworkAdapter::factory()->create();

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
            'network_id' => 'mock-realtime',
            'external_store_id' => 'S-001',
            'balance_centavos' => 50000,
            'reserved_centavos' => 0,
        ]);

        return [$merchant, $reference, $wallet];
    }

    private function validatePayload(Merchant $merchant, PaymentReference $reference, array $overrides = []): array
    {
        return array_merge([
            'contract_v' => 1,
            'network_id' => 'mock-realtime',
            'mid' => $merchant->mid,
            'ref' => $reference->reference_token,
            'amount_attempt' => 10000,
            'store_id' => 'S-001',
            'terminal_id' => 'T-01',
            'request_id' => 'REQ-'.uniqid(),
        ], $overrides);
    }

    private function confirmPayload(Merchant $merchant, PaymentReference $reference, array $overrides = []): array
    {
        return array_merge([
            'contract_v' => 1,
            'network_id' => 'mock-realtime',
            'mid' => $merchant->mid,
            'ref' => $reference->reference_token,
            'amount_paid' => 10000,
            'is_partial' => false,
            'network_txn_id' => 'NTX-'.uniqid(),
            'idempotency_key' => 'IDEMP-'.uniqid(),
            'store_id' => 'S-001',
            'terminal_id' => 'T-01',
            'collected_at' => now()->getTimestamp(),
        ], $overrides);
    }

    public function test_validate_then_confirm_round_trips_through_the_contract(): void
    {
        [$merchant, $reference, $wallet] = $this->setUpFixture();
        $adapter = new MockRealtimeAdapter();

        $validateResult = $adapter->validate($this->validatePayload($merchant, $reference));

        $this->assertTrue($validateResult['ok']);
        $this->assertNotEmpty($validateResult['reservation_id']);
        $this->assertSame(10000, $wallet->fresh()->reserved_centavos);

        $confirmResult = $adapter->confirm($this->confirmPayload($merchant, $reference));

        $this->assertTrue($confirmResult['ok']);
        $this->assertNotEmpty($confirmResult['transaction_public_id']);
        $this->assertNotEmpty($confirmResult['auth_code']);
        $this->assertSame(10000, $confirmResult['receipt']['amount']);
        $this->assertSame('CONFIRMED', Transaction::first()->state);
    }

    public function test_simulated_timeout_throws_before_touching_core(): void
    {
        [$merchant, $reference] = $this->setUpFixture();
        $adapter = new MockRealtimeAdapter(simulateTimeout: true);

        $this->expectException(MockRealtimeTimeoutException::class);

        try {
            $adapter->validate($this->validatePayload($merchant, $reference));
        } finally {
            $this->assertSame(0, Transaction::count());
        }
    }

    public function test_duplicate_network_txn_id_returns_the_stored_result_not_a_second_posting(): void
    {
        [$merchant, $reference] = $this->setUpFixture();
        $adapter = new MockRealtimeAdapter();
        $adapter->validate($this->validatePayload($merchant, $reference));
        $payload = $this->confirmPayload($merchant, $reference);

        $first = $adapter->confirm($payload);
        $second = $adapter->confirm($payload);

        $this->assertTrue($first['ok']);
        $this->assertSame($first, $second);
        $this->assertSame(1, Transaction::where('state', 'CONFIRMED')->count());
    }

    public function test_tampered_mid_is_declined_not_thrown(): void
    {
        [$merchant, $reference] = $this->setUpFixture();
        $adapter = new MockRealtimeAdapter();

        $result = $adapter->validate($this->validatePayload($merchant, $reference, ['mid' => '999999999']));

        $this->assertFalse($result['ok']);
        $this->assertSame('tampered', $result['decline_reason']);
        $this->assertSame(0, Transaction::count());
    }
}
