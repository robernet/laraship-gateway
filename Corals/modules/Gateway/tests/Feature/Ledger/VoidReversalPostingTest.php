<?php

namespace Tests\Feature\Ledger;

use Corals\Modules\Gateway\Core\Ledger\Postings\ConfirmedCollectionPosting;
use Corals\Modules\Gateway\Core\Ledger\Postings\VoidReversalPosting;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\LedgerEntry;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use RuntimeException;

class VoidReversalPostingTest extends GatewayTestCase
{
    private function confirmCollection(PosWallet $wallet, Issuer $issuer, int $transactionId = 1): string
    {
        return (new ConfirmedCollectionPosting())->apply(
            transactionId: $transactionId,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: 10000,
            commissionCentavos: 150,
            feeCentavos: 50
        );
    }

    public function test_posting_is_balanced(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);
        $issuer = Issuer::factory()->create();
        $originalPostingId = $this->confirmCollection($wallet, $issuer);

        $voidPostingId = (new VoidReversalPosting())->apply($originalPostingId, isFinalized: false);

        $legs = LedgerEntry::where('posting_id', $voidPostingId)->get();

        $this->assertSame(10000, (int) $legs->where('direction', 'debit')->sum('amount_centavos'));
        $this->assertSame(10000, (int) $legs->where('direction', 'credit')->sum('amount_centavos'));
    }

    public function test_wallet_balance_stays_non_negative_and_correct(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);
        $issuer = Issuer::factory()->create();
        $originalPostingId = $this->confirmCollection($wallet, $issuer);

        (new VoidReversalPosting())->apply($originalPostingId, isFinalized: false);

        $wallet->refresh();

        $this->assertSame(10000, $wallet->balance_centavos);
        $this->assertGreaterThanOrEqual(0, $wallet->balance_centavos);
    }

    public function test_reversal_is_exact_equal_and_opposite_of_original(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);
        $issuer = Issuer::factory()->create();
        $originalPostingId = $this->confirmCollection($wallet, $issuer);

        $originalLegs = LedgerEntry::where('posting_id', $originalPostingId)->get();

        $voidPostingId = (new VoidReversalPosting())->apply($originalPostingId, isFinalized: false);

        $voidLegs = LedgerEntry::where('posting_id', $voidPostingId)->get();

        $this->assertSame($originalLegs->count(), $voidLegs->count());

        foreach ($originalLegs as $originalLeg) {
            $mirrored = $voidLegs->first(fn ($leg) => $leg->account_type === $originalLeg->account_type
                && $leg->account_ref === $originalLeg->account_ref);

            $this->assertNotNull($mirrored, "No mirrored leg for {$originalLeg->account_type}");
            $this->assertSame($originalLeg->amount_centavos, $mirrored->amount_centavos);
            $this->assertNotSame($originalLeg->direction, $mirrored->direction);
        }
    }

    public function test_blocked_post_finalized_except_via_exceptions_path(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);
        $issuer = Issuer::factory()->create();
        $originalPostingId = $this->confirmCollection($wallet, $issuer);

        try {
            (new VoidReversalPosting())->apply($originalPostingId, isFinalized: true, viaExceptionsPath: false);
            $this->fail('Expected the finalized void to be blocked.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('finalized', $e->getMessage());
        }

        $wallet->refresh();
        $this->assertSame(0, $wallet->balance_centavos);
        $this->assertSame(4, LedgerEntry::count());

        $voidPostingId = (new VoidReversalPosting())->apply($originalPostingId, isFinalized: true, viaExceptionsPath: true);

        $this->assertSame(8, LedgerEntry::count());
        $wallet->refresh();
        $this->assertSame(10000, $wallet->balance_centavos);
        $this->assertNotSame($originalPostingId, $voidPostingId);
    }

    public function test_replaying_the_same_void_does_not_double_post(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);
        $issuer = Issuer::factory()->create();
        $originalPostingId = $this->confirmCollection($wallet, $issuer);

        $posting = new VoidReversalPosting();

        $first = $posting->apply($originalPostingId, isFinalized: false);
        $second = $posting->apply($originalPostingId, isFinalized: false);

        $this->assertSame($first, $second);
        $this->assertSame(8, LedgerEntry::count());

        $wallet->refresh();
        $this->assertSame(10000, $wallet->balance_centavos);
    }

    public function test_wallet_balance_matches_net_ledger_position(): void
    {
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);
        $issuer = Issuer::factory()->create();
        $originalPostingId = $this->confirmCollection($wallet, $issuer);

        (new VoidReversalPosting())->apply($originalPostingId, isFinalized: false);

        $wallet->refresh();

        $netLedgerPosition = LedgerEntry::where('account_type', 'pos_wallet')
            ->where('account_ref', (string) $wallet->id)
            ->get()
            ->sum(fn ($entry) => $entry->direction === 'credit' ? $entry->amount_centavos : -$entry->amount_centavos);

        $this->assertSame($wallet->balance_centavos - 10000, (int) $netLedgerPosition);
    }
}
