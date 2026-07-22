<?php

namespace Corals\Modules\Gateway\Core\Ledger\Postings;

use Corals\Modules\Gateway\Models\LedgerEntry;
use Corals\Modules\Gateway\Models\WalletTopUp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Applies a matched wallet top-up: credits the pos_wallet for the funded
 * amount and debits a suspense account representing the inbound SPEI
 * clearing funds. See docs/settlement-reconciliation.md ("Top-up ingestion
 * & matching" — "apply (ledger credit, status=applied)") and
 * docs/data-model.md (ledger_entries, wallet_top_ups).
 *
 * Idempotent on the top-up's own status: the conditional
 * pending -> applied UPDATE is the idempotency guard (mirrors the wallet
 * overdraft conditional-update pattern) — a replay against an
 * already-applied top-up returns the existing posting_id instead of
 * writing a second posting.
 */
class TopupAppliedPosting
{
    public function apply(WalletTopUp $topUp): string
    {
        return DB::transaction(function () use ($topUp) {
            $applied = DB::table('wallet_top_ups')
                ->where('id', $topUp->id)
                ->where('status', 'pending')
                ->update(['status' => 'applied', 'applied_at' => now(), 'updated_at' => now()]);

            if ($applied === 0) {
                $existingPostingId = LedgerEntry::where('top_up_id', $topUp->id)->value('posting_id');

                if ($existingPostingId === null) {
                    throw new RuntimeException("Top-up {$topUp->id} is not pending and has no existing posting.");
                }

                return $existingPostingId;
            }

            $postingId = (string) Str::uuid();
            $amount = (int) $topUp->amount_centavos;

            $walletUpdated = DB::table('pos_wallets')
                ->where('id', $topUp->pos_wallet_id)
                ->update(['balance_centavos' => DB::raw("balance_centavos + {$amount}")]);

            if ($walletUpdated === 0) {
                throw new RuntimeException("pos_wallet {$topUp->pos_wallet_id} not found.");
            }

            $legs = [
                [
                    'posting_id' => $postingId,
                    'account_type' => 'suspense',
                    'account_ref' => 'spei_inbound',
                    'direction' => 'debit',
                    'amount_centavos' => $amount,
                    'top_up_id' => $topUp->id,
                    'created_at' => now(),
                ],
                [
                    'posting_id' => $postingId,
                    'account_type' => 'pos_wallet',
                    'account_ref' => (string) $topUp->pos_wallet_id,
                    'direction' => 'credit',
                    'amount_centavos' => $amount,
                    'top_up_id' => $topUp->id,
                    'created_at' => now(),
                ],
            ];

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
