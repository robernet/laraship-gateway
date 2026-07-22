<?php

namespace Corals\Modules\Gateway\Core\Ledger\Postings;

use Corals\Modules\Gateway\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Voids a confirmed-collection posting: an exact equal-and-opposite balanced
 * posting (credit wallet back / debit issuer_payable + commission + fee).
 * See docs/state-machines.md ("CONFIRMED → VOIDED (void window only)") and
 * docs/settlement-reconciliation.md ("Finality & reversal") — cash is never
 * clawed back, so reversing the wallet leg is a plain credit (no overdraft
 * risk).
 *
 * Blocked once the original transaction is FINALIZED, except via the
 * reconciliation_exceptions path ($viaExceptionsPath = true).
 *
 * Idempotent on the original posting's transaction_id: a replay against an
 * already-voided posting returns the existing reversal posting_id instead of
 * writing a second one. (ledger_entries has no "reverses" column yet, so the
 * shared transaction_id is what links a posting to its reversal — valid
 * because a transaction has at most one confirmed_collection posting.)
 */
class VoidReversalPosting
{
    public function apply(string $originalPostingId, bool $isFinalized, bool $viaExceptionsPath = false): string
    {
        return DB::transaction(function () use ($originalPostingId, $isFinalized, $viaExceptionsPath) {
            $originalLegs = LedgerEntry::where('posting_id', $originalPostingId)->get();

            if ($originalLegs->isEmpty()) {
                throw new RuntimeException("No posting found for {$originalPostingId}.");
            }

            $transactionId = $originalLegs->first()->transaction_id;

            $existingReversalPostingId = LedgerEntry::where('transaction_id', $transactionId)
                ->where('posting_id', '!=', $originalPostingId)
                ->value('posting_id');

            if ($existingReversalPostingId !== null) {
                return $existingReversalPostingId;
            }

            if ($isFinalized && ! $viaExceptionsPath) {
                throw new RuntimeException(
                    "Posting {$originalPostingId} is finalized; void requires the exceptions path."
                );
            }

            $walletLeg = $originalLegs->firstWhere('account_type', 'pos_wallet');

            if ($walletLeg !== null) {
                DB::table('pos_wallets')
                    ->where('id', (int) $walletLeg->account_ref)
                    ->increment('balance_centavos', $walletLeg->amount_centavos);
            }

            $postingId = (string) Str::uuid();

            $legs = $originalLegs->map(fn ($leg) => [
                'posting_id' => $postingId,
                'account_type' => $leg->account_type,
                'account_ref' => $leg->account_ref,
                'direction' => $leg->direction === 'debit' ? 'credit' : 'debit',
                'amount_centavos' => $leg->amount_centavos,
                'transaction_id' => $leg->transaction_id,
                'created_at' => now(),
            ])->all();

            $this->assertBalanced($legs);

            DB::table('ledger_entries')->insert($legs);

            return $postingId;
        });
    }

    private function assertBalanced(array $legs): void
    {
        $debits = 0;
        $credits = 0;

        foreach ($legs as $leg) {
            if ($leg['direction'] === 'debit') {
                $debits += $leg['amount_centavos'];
            } else {
                $credits += $leg['amount_centavos'];
            }
        }

        if ($debits !== $credits) {
            throw new RuntimeException("Unbalanced posting: debits={$debits} credits={$credits}");
        }
    }
}
