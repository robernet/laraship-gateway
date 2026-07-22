<?php

namespace Corals\Modules\Gateway\Core\Ledger\Postings;

use Corals\Modules\Gateway\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Books a confirmed cash collection: debits the collecting pos_wallet and
 * credits issuer_payable net of commission/fee, plus the commission and fee
 * legs. See docs/data-model.md ("Canonical confirmed-collection posting")
 * and docs/settlement-reconciliation.md ("Confirmed-collection posting").
 *
 * Idempotent on transaction_id: a replay for a transaction that already has
 * a booked posting returns the existing posting_id instead of writing a
 * second one. Overdraft guard mirrors the wallet top-up pattern: the wallet
 * debit is a conditional UPDATE (`balance_centavos >= amount`); 0 rows
 * affected means insufficient funds and nothing is written — no ledger rows
 * are inserted before that check.
 */
class ConfirmedCollectionPosting
{
    public function apply(
        int $transactionId,
        int $posWalletId,
        int $issuerId,
        int $amountCentavos,
        int $commissionCentavos,
        int $feeCentavos
    ): string {
        return DB::transaction(function () use (
            $transactionId,
            $posWalletId,
            $issuerId,
            $amountCentavos,
            $commissionCentavos,
            $feeCentavos
        ) {
            $existingPostingId = LedgerEntry::where('transaction_id', $transactionId)
                ->where('account_type', 'pos_wallet')
                ->value('posting_id');

            if ($existingPostingId !== null) {
                return $existingPostingId;
            }

            $walletUpdated = DB::table('pos_wallets')
                ->where('id', $posWalletId)
                ->where('balance_centavos', '>=', $amountCentavos)
                ->update(['balance_centavos' => DB::raw("balance_centavos - {$amountCentavos}")]);

            if ($walletUpdated === 0) {
                throw new RuntimeException("pos_wallet {$posWalletId} has insufficient funds for {$amountCentavos}.");
            }

            $postingId = (string) Str::uuid();
            $netToIssuer = $amountCentavos - $commissionCentavos - $feeCentavos;

            $legs = [
                [
                    'posting_id' => $postingId,
                    'account_type' => 'pos_wallet',
                    'account_ref' => (string) $posWalletId,
                    'direction' => 'debit',
                    'amount_centavos' => $amountCentavos,
                    'transaction_id' => $transactionId,
                    'created_at' => now(),
                ],
                [
                    'posting_id' => $postingId,
                    'account_type' => 'issuer_payable',
                    'account_ref' => (string) $issuerId,
                    'direction' => 'credit',
                    'amount_centavos' => $netToIssuer,
                    'transaction_id' => $transactionId,
                    'created_at' => now(),
                ],
                [
                    'posting_id' => $postingId,
                    'account_type' => 'network_commission',
                    'account_ref' => 'network',
                    'direction' => 'credit',
                    'amount_centavos' => $commissionCentavos,
                    'transaction_id' => $transactionId,
                    'created_at' => now(),
                ],
                [
                    'posting_id' => $postingId,
                    'account_type' => 'gateway_fee',
                    'account_ref' => 'gateway',
                    'direction' => 'credit',
                    'amount_centavos' => $feeCentavos,
                    'transaction_id' => $transactionId,
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
