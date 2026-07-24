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

class IngestSettlementBatchTest extends GatewayTestCase
{
    private const NETWORK = 'mock-batch';

    private function setUpFixture(array $intentOverrides = []): array
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
        $reference = PaymentReference::factory()->create([
            'payment_intent_id' => $intent->id,
            'status' => 'active',
            'expires_at' => now()->addHour(),
        ]);

        return [$merchant, $intent, $reference];
    }

    private function wallet(string $networkId = self::NETWORK): PosWallet
    {
        return PosWallet::factory()->create([
            'network_id' => $networkId,
            'external_store_id' => 'S-BATCH',
            'balance_centavos' => 50000,
            'reserved_centavos' => 0,
            'status' => 'active',
        ]);
    }

    private function row(Merchant $merchant, PaymentReference $reference, array $overrides = []): array
    {
        return array_merge([
            'network_txn_id' => 'NTX-'.uniqid(),
            'mid' => $merchant->mid,
            'ref' => $reference->reference_token,
            'amount_paid' => 10000,
            'collected_at' => now()->timestamp,
        ], $overrides);
    }

    private function actingAsNetwork(): NetworkCredential
    {
        $credential = NetworkCredential::factory()->create();
        Sanctum::actingAs($credential, [NetworkAbility::BatchConfirm->value]);

        return $credential;
    }

    private function batchConfirm(array $payload)
    {
        return $this->postJson('/api/v1/cash/batch-confirm', $payload);
    }

    private function minimalPayload(array $rows = []): array
    {
        return [
            'contract_v' => 1,
            'network_id' => self::NETWORK,
            'batch_id' => 'BATCH-'.uniqid(),
            'rows' => $rows,
        ];
    }

    public function test_happy_path_confirms_two_rows_for_two_intents(): void
    {
        $this->wallet();
        [$merchant1, $intent1, $reference1] = $this->setUpFixture();
        [$merchant2, $intent2, $reference2] = $this->setUpFixture();
        $this->actingAsNetwork();

        $response = $this->batchConfirm($this->minimalPayload([
            $this->row($merchant1, $reference1),
            $this->row($merchant2, $reference2),
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('confirmed_count', 2);
        $response->assertJsonPath('exception_count', 0);
        $this->assertSame(2, Transaction::where('state', 'CONFIRMED')->where('finality', 'batch')->count());
        $this->assertSame('PAID_PENDING_SETTLEMENT', $intent1->fresh()->state);
        $this->assertSame('PAID_PENDING_SETTLEMENT', $intent2->fresh()->state);
    }

    public function test_bad_row_lands_as_reconciliation_exception_without_aborting_batch(): void
    {
        $this->wallet();
        [$merchant, , $reference] = $this->setUpFixture();
        $this->actingAsNetwork();

        $badRow = $this->row($merchant, $reference, ['mid' => '999999999', 'ref' => '9999999999']);
        $goodRow = $this->row($merchant, $reference);

        $response = $this->batchConfirm($this->minimalPayload([$badRow, $goodRow]));

        $response->assertStatus(200);
        $response->assertJsonPath('confirmed_count', 1);
        $response->assertJsonPath('exception_count', 1);
        $response->assertJsonPath('exceptions.0.network_txn_id', $badRow['network_txn_id']);
        $response->assertJsonPath('exceptions.0.type', 'unmatched_confirm');
        $this->assertSame(1, ReconciliationException::where('type', 'unmatched_confirm')->count());
        $this->assertSame(1, Transaction::where('state', 'CONFIRMED')->count());
    }

    public function test_row_is_unmatched_when_no_active_wallet_exists_for_the_network(): void
    {
        // No PosWallet created for self::NETWORK — the design decision is that
        // a batch row with zero (or more than one) active wallet for its
        // network is unresolvable and becomes an unmatched_confirm exception
        // rather than a hard error, since the contract has no store_id to
        // disambiguate with.
        [$merchant, , $reference] = $this->setUpFixture();
        $this->actingAsNetwork();

        $response = $this->batchConfirm($this->minimalPayload([$this->row($merchant, $reference)]));

        $response->assertJsonPath('confirmed_count', 0);
        $response->assertJsonPath('exception_count', 1);
        $response->assertJsonPath('exceptions.0.reason', 'wallet_not_found');
        $response->assertJsonPath('exceptions.0.type', 'unmatched_confirm');
    }

    public function test_replaying_the_same_network_txn_id_is_idempotent_with_no_second_posting(): void
    {
        $this->wallet();
        [$merchant, , $reference] = $this->setUpFixture();
        $this->actingAsNetwork();

        $row = $this->row($merchant, $reference);
        $payload = $this->minimalPayload([$row]);

        $first = $this->batchConfirm($payload);
        $second = $this->batchConfirm($this->minimalPayload([$row]));

        $first->assertJsonPath('confirmed_count', 1);
        $second->assertJsonPath('confirmed_count', 1);
        $second->assertJsonPath('exception_count', 0);
        $this->assertSame(1, Transaction::where('state', 'CONFIRMED')->count());
        $this->assertSame(1, LedgerEntry::where('account_type', 'pos_wallet')->count());
        $this->assertSame(1, OutboxEvent::count());
    }

    public function test_missing_ability_is_rejected(): void
    {
        $credential = NetworkCredential::factory()->create();
        Sanctum::actingAs($credential, []);

        $this->batchConfirm($this->minimalPayload())->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->batchConfirm($this->minimalPayload())->assertStatus(401);
    }

    public function test_missing_rows_returns_422(): void
    {
        $this->actingAsNetwork();

        $this->postJson('/api/v1/cash/batch-confirm', [
            'contract_v' => 1,
            'network_id' => self::NETWORK,
            'batch_id' => 'BATCH-'.uniqid(),
        ])->assertStatus(422);
    }
}
