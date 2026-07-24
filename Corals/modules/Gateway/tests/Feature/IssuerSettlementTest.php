<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\Ledger\Postings\ConfirmedCollectionPosting;
use Corals\Modules\Gateway\Core\Settlement\IssuerSettlement;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\LedgerEntry;
use Corals\Modules\Gateway\Models\OutboxEvent;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\Settlement;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use RuntimeException;

class IssuerSettlementTest extends GatewayTestCase
{
    public function test_settles_accrued_issuer_payable_and_zeroes_the_position(): void
    {
        $issuer = Issuer::factory()->create();
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);

        (new ConfirmedCollectionPosting())->apply(
            transactionId: 1,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: 10000,
            commissionCentavos: 150,
            feeCentavos: 50
        );

        $result = (new IssuerSettlement())->settle($issuer->id, '2026-07');

        $this->assertTrue($result['ok']);
        $this->assertNotNull($result['posting_id']);
        $this->assertSame(9800, $result['net_centavos']);
        $this->assertNotNull($result['spei_ref']);

        $settlement = Settlement::find($result['settlement_id']);
        $this->assertSame($issuer->id, $settlement->issuer_id);
        $this->assertSame('2026-07', $settlement->period);
        $this->assertSame(10000, $settlement->gross_centavos);
        $this->assertSame(150, $settlement->commission_centavos);
        $this->assertSame(50, $settlement->fee_centavos);
        $this->assertSame(9800, $settlement->net_centavos);
        $this->assertSame('completed', $settlement->status);
        $this->assertNotNull($settlement->reconciled_at);

        $netIssuerPayable = LedgerEntry::where('account_type', 'issuer_payable')
            ->where('account_ref', (string) $issuer->id)
            ->get()
            ->sum(fn ($entry) => $entry->direction === 'credit' ? $entry->amount_centavos : -$entry->amount_centavos);

        $this->assertSame(0, (int) $netIssuerPayable);

        $event = OutboxEvent::where('event', 'settlement.completed')->first();
        $this->assertNotNull($event);
        $this->assertSame($settlement->id, $event->payload['settlement_id']);
    }

    public function test_settling_again_with_nothing_accrued_throws_and_writes_nothing(): void
    {
        $issuer = Issuer::factory()->create();
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);

        (new ConfirmedCollectionPosting())->apply(
            transactionId: 1,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: 10000,
            commissionCentavos: 150,
            feeCentavos: 50
        );

        (new IssuerSettlement())->settle($issuer->id, '2026-07');

        $settlementCountBefore = Settlement::count();

        try {
            (new IssuerSettlement())->settle($issuer->id, '2026-07');
            $this->fail('Expected "nothing to settle" was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('No unsettled issuer_payable', $e->getMessage());
        }

        $this->assertSame($settlementCountBefore, Settlement::count());
    }

    public function test_a_second_period_only_settles_newly_accrued_payable(): void
    {
        $issuer = Issuer::factory()->create();
        $wallet = PosWallet::factory()->create(['balance_centavos' => 20000]);

        (new ConfirmedCollectionPosting())->apply(
            transactionId: 1,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: 10000,
            commissionCentavos: 150,
            feeCentavos: 50
        );

        $first = (new IssuerSettlement())->settle($issuer->id, '2026-07');

        (new ConfirmedCollectionPosting())->apply(
            transactionId: 2,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: 5000,
            commissionCentavos: 75,
            feeCentavos: 25
        );

        $second = (new IssuerSettlement())->settle($issuer->id, '2026-08');

        $this->assertNotSame($first['settlement_id'], $second['settlement_id']);
        $this->assertSame(4900, $second['net_centavos']);

        $netIssuerPayable = LedgerEntry::where('account_type', 'issuer_payable')
            ->where('account_ref', (string) $issuer->id)
            ->get()
            ->sum(fn ($entry) => $entry->direction === 'credit' ? $entry->amount_centavos : -$entry->amount_centavos);

        $this->assertSame(0, (int) $netIssuerPayable);
    }
}
