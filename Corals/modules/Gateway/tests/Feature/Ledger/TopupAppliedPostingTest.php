<?php

namespace Tests\Feature\Ledger;

use Corals\Modules\Gateway\Core\Ledger\Postings\TopupAppliedPosting;
use Corals\Modules\Gateway\Models\LedgerEntry;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\WalletTopUp;
use Corals\Modules\Gateway\tests\GatewayTestCase;

class TopupAppliedPostingTest extends GatewayTestCase
{
    public function test_posting_is_balanced(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 0]);
        $topUp = WalletTopUp::factory()->create([
            'pos_wallet_id' => $wallet->id,
            'amount_centavos' => 15000,
            'status' => 'pending',
        ]);

        $postingId = (new TopupAppliedPosting())->apply($topUp);

        $legs = LedgerEntry::where('posting_id', $postingId)->get();

        $this->assertSame(15000, (int) $legs->where('direction', 'debit')->sum('amount_centavos'));
        $this->assertSame(15000, (int) $legs->where('direction', 'credit')->sum('amount_centavos'));
    }

    public function test_wallet_balance_stays_non_negative_and_correct(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 5000]);
        $topUp = WalletTopUp::factory()->create([
            'pos_wallet_id' => $wallet->id,
            'amount_centavos' => 15000,
            'status' => 'pending',
        ]);

        (new TopupAppliedPosting())->apply($topUp);

        $wallet->refresh();

        $this->assertSame(20000, $wallet->balance_centavos);
        $this->assertGreaterThanOrEqual(0, $wallet->balance_centavos);
    }

    public function test_replaying_the_same_top_up_does_not_double_post(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 0]);
        $topUp = WalletTopUp::factory()->create([
            'pos_wallet_id' => $wallet->id,
            'amount_centavos' => 15000,
            'status' => 'pending',
        ]);

        $posting = new TopupAppliedPosting();

        $first = $posting->apply($topUp);
        $second = $posting->apply($topUp->fresh());

        $this->assertSame($first, $second);
        $this->assertSame(
            1,
            LedgerEntry::where('top_up_id', $topUp->id)->where('direction', 'credit')->count()
        );

        $wallet->refresh();
        $this->assertSame(15000, $wallet->balance_centavos);
    }

    public function test_wallet_balance_matches_net_ledger_position(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 0]);
        $topUp = WalletTopUp::factory()->create([
            'pos_wallet_id' => $wallet->id,
            'amount_centavos' => 15000,
            'status' => 'pending',
        ]);

        (new TopupAppliedPosting())->apply($topUp);

        $wallet->refresh();

        $netLedgerPosition = LedgerEntry::where('account_type', 'pos_wallet')
            ->where('account_ref', (string) $wallet->id)
            ->get()
            ->sum(fn ($entry) => $entry->direction === 'credit' ? $entry->amount_centavos : -$entry->amount_centavos);

        $this->assertSame($wallet->balance_centavos, (int) $netLedgerPosition);
    }
}
