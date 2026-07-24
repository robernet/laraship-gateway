<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\Networks\NetworkAbility;
use Corals\Modules\Gateway\Models\LedgerEntry;
use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\Models\NetworkCredential;
use Corals\Modules\Gateway\Models\OutboxEvent;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\Models\PaymentReference;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\ReconciliationException;
use Corals\Modules\Gateway\Models\Transaction;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use Laravel\Sanctum\Sanctum;

class ConfirmCollectionTest extends GatewayTestCase
{
    private function setUpFixture(array $intentOverrides = [], array $referenceOverrides = []): array
    {
        $merchant = Merchant::factory()->create();
        $intent = PaymentIntent::factory()->create(array_merge([
            'merchant_id' => $merchant->id,
            'state' => 'ACTIVE',
            'mode' => 'one_time',
            'amount_policy' => ['type' => 'fixed', 'amount' => 10000, 'allow_partial' => false],
            'overpay_policy' => 'reject',
            'underpay_policy' => 'reject',
        ], $intentOverrides));
        $reference = PaymentReference::factory()->create(array_merge([
            'payment_intent_id' => $intent->id,
            'status' => 'active',
            'expires_at' => now()->addHour(),
        ], $referenceOverrides));
        $wallet = PosWallet::factory()->create([
            'network_id' => 'mock-realtime',
            'external_store_id' => 'S-001',
            'balance_centavos' => 50000,
            'reserved_centavos' => 0,
        ]);

        return [$merchant, $intent, $reference, $wallet];
    }

    private function payload(Merchant $merchant, PaymentReference $reference, array $overrides = []): array
    {
        return array_merge([
            'contract_v' => 1,
            'network_id' => 'mock-realtime',
            'mid' => $merchant->mid,
            'ref' => $reference->reference_token,
            'amount_paid' => 10000,
            'is_partial' => false,
            'network_txn_id' => 'NTX-'.uniqid(),
            'idempotency_key' => 'IDEM-'.uniqid(),
            'store_id' => 'S-001',
            'terminal_id' => 'T-01',
            'collected_at' => now()->timestamp,
        ], $overrides);
    }

    private function actingAsNetwork(): NetworkCredential
    {
        $credential = NetworkCredential::factory()->create();
        Sanctum::actingAs($credential, [NetworkAbility::ConfirmCollection->value]);

        return $credential;
    }

    private function confirm(array $payload)
    {
        return $this->postJson('/api/v1/cash/confirm', $payload, ['Idempotency-Key' => $payload['idempotency_key']]);
    }

    public function test_happy_path_confirms_a_prior_reservation_and_posts_the_ledger(): void
    {
        [$merchant, $intent, $reference, $wallet] = $this->setUpFixture();
        $transaction = Transaction::factory()->create([
            'payment_reference_id' => $reference->id,
            'pos_wallet_id' => $wallet->id,
            'amount_centavos' => 10000,
            'state' => 'AUTHORIZED',
        ]);
        $wallet->update(['reserved_centavos' => 10000]);
        $this->actingAsNetwork();

        $response = $this->confirm($this->payload($merchant, $reference));

        $response->assertStatus(200);
        $response->assertJsonPath('ok', true);
        $this->assertNotEmpty($response->json('transaction_public_id'));

        $transaction->refresh();
        $this->assertSame('CONFIRMED', $transaction->state);
        $this->assertSame(0, $wallet->fresh()->reserved_centavos);
        $this->assertSame('PAID_PENDING_SETTLEMENT', $intent->fresh()->state);
        $this->assertSame('consumed', $reference->fresh()->status);
        $this->assertSame(1, OutboxEvent::where('event', 'payment.confirmed')->count());
    }

    public function test_no_prior_reservation_creates_the_authorized_row_inline(): void
    {
        [$merchant, $intent, $reference, $wallet] = $this->setUpFixture();
        $this->actingAsNetwork();

        $response = $this->confirm($this->payload($merchant, $reference));

        $response->assertJsonPath('ok', true);
        $this->assertSame(1, Transaction::where('state', 'CONFIRMED')->count());
    }

    public function test_replaying_the_same_network_txn_id_returns_the_stored_result_with_no_second_posting(): void
    {
        [$merchant, $intent, $reference, $wallet] = $this->setUpFixture();
        $this->actingAsNetwork();
        $payload = $this->payload($merchant, $reference);

        $first = $this->confirm($payload);
        $second = $this->confirm($payload);

        $first->assertJsonPath('ok', true);
        $this->assertSame($first->json('transaction_public_id'), $second->json('transaction_public_id'));
        $this->assertSame(1, LedgerEntry::where('account_type', 'pos_wallet')->count());
        $this->assertSame(1, OutboxEvent::count());
    }

    public function test_one_time_reference_confirms_exactly_once_under_concurrent_network_txn_ids(): void
    {
        [$merchant, $intent, $reference, $wallet] = $this->setUpFixture();
        $this->actingAsNetwork();

        $first = $this->confirm($this->payload($merchant, $reference));
        $second = $this->confirm($this->payload($merchant, $reference));

        $first->assertJsonPath('ok', true);
        $second->assertJsonPath('ok', false);
        $second->assertJsonPath('error', 'reference_already_consumed');

        $this->assertSame(1, Transaction::where('state', 'CONFIRMED')->count());
        $this->assertSame(1, LedgerEntry::where('account_type', 'pos_wallet')->count());
    }

    public function test_reusable_reference_allows_a_second_confirm_and_fires_payment_credited(): void
    {
        [$merchant, $intent, $reference, $wallet] = $this->setUpFixture([
            'mode' => 'reusable',
            'amount_policy' => ['type' => 'variable', 'min' => 100, 'max' => 20000, 'allow_partial' => true],
        ]);
        $this->actingAsNetwork();

        $first = $this->confirm($this->payload($merchant, $reference, ['amount_paid' => 5000]));
        $second = $this->confirm($this->payload($merchant, $reference, ['amount_paid' => 5000, 'is_partial' => true]));

        $first->assertJsonPath('ok', true);
        $second->assertJsonPath('ok', true);
        $this->assertSame(2, Transaction::where('state', 'CONFIRMED')->count());
        $this->assertSame(1, OutboxEvent::where('event', 'payment.confirmed')->count());
        $this->assertSame(1, OutboxEvent::where('event', 'payment.credited')->count());
    }

    public function test_amount_mismatch_is_rejected_by_default_policy(): void
    {
        [$merchant, $intent, $reference, $wallet] = $this->setUpFixture();
        $this->actingAsNetwork();

        $response = $this->confirm($this->payload($merchant, $reference, ['amount_paid' => 9999]));

        $response->assertJsonPath('ok', false);
        $response->assertJsonPath('error', 'amount_mismatch');
        $this->assertSame(0, Transaction::where('state', 'CONFIRMED')->count());
    }

    public function test_overpay_accept_and_flag_posts_and_opens_a_reconciliation_exception(): void
    {
        [$merchant, $intent, $reference, $wallet] = $this->setUpFixture(['overpay_policy' => 'accept_and_flag']);
        $this->actingAsNetwork();

        $response = $this->confirm($this->payload($merchant, $reference, ['amount_paid' => 10500]));

        $response->assertJsonPath('ok', true);
        $this->assertSame(1, ReconciliationException::where('type', 'amount_mismatch')->count());
    }

    public function test_missing_ability_is_rejected(): void
    {
        [$merchant, $intent, $reference] = $this->setUpFixture();
        $credential = NetworkCredential::factory()->create();
        Sanctum::actingAs($credential, []);

        $this->confirm($this->payload($merchant, $reference))->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        [$merchant, $intent, $reference] = $this->setUpFixture();

        $this->confirm($this->payload($merchant, $reference))->assertStatus(401);
    }

    public function test_missing_idempotency_key_header_returns_422(): void
    {
        [$merchant, $intent, $reference] = $this->setUpFixture();
        $this->actingAsNetwork();

        $this->postJson('/api/v1/cash/confirm', $this->payload($merchant, $reference))->assertStatus(422);
    }
}
