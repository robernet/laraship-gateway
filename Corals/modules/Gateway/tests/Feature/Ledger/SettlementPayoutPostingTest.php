<?php

namespace Tests\Feature\Ledger;

use Corals\Modules\Gateway\Core\Ledger\Postings\SettlementPayoutPosting;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\LedgerEntry;
use Corals\Modules\Gateway\tests\GatewayTestCase;

class SettlementPayoutPostingTest extends GatewayTestCase
{
    public function test_posting_is_balanced(): void
    {
        $issuer = Issuer::factory()->create();

        $postingId = (new SettlementPayoutPosting())->apply(
            settlementId: 1,
            issuerId: $issuer->id,
            netCentavos: 9800
        );

        $legs = LedgerEntry::where('posting_id', $postingId)->get();

        $this->assertSame(9800, (int) $legs->where('direction', 'debit')->sum('amount_centavos'));
        $this->assertSame(9800, (int) $legs->where('direction', 'credit')->sum('amount_centavos'));

        $debitLeg = $legs->firstWhere('direction', 'debit');
        $this->assertSame('issuer_payable', $debitLeg->account_type);
        $this->assertSame((string) $issuer->id, $debitLeg->account_ref);

        $creditLeg = $legs->firstWhere('direction', 'credit');
        $this->assertSame('suspense', $creditLeg->account_type);
        $this->assertSame('spei_outbound', $creditLeg->account_ref);
    }

    public function test_replaying_the_same_settlement_does_not_double_post(): void
    {
        $issuer = Issuer::factory()->create();
        $posting = new SettlementPayoutPosting();

        $first = $posting->apply(settlementId: 1, issuerId: $issuer->id, netCentavos: 9800);
        $second = $posting->apply(settlementId: 1, issuerId: $issuer->id, netCentavos: 9800);

        $this->assertSame($first, $second);
        $this->assertSame(
            1,
            LedgerEntry::where('settlement_id', 1)->where('account_type', 'issuer_payable')->count()
        );
    }

    public function test_issuer_payable_position_matches_net_ledger_position(): void
    {
        $issuer = Issuer::factory()->create();

        (new SettlementPayoutPosting())->apply(
            settlementId: 1,
            issuerId: $issuer->id,
            netCentavos: 9800
        );

        $netLedgerPosition = LedgerEntry::where('account_type', 'issuer_payable')
            ->where('account_ref', (string) $issuer->id)
            ->get()
            ->sum(fn ($entry) => $entry->direction === 'credit' ? $entry->amount_centavos : -$entry->amount_centavos);

        $this->assertSame(-9800, (int) $netLedgerPosition);
    }
}
