<?php

namespace Corals\Modules\Gateway\Core\Ledger\Postings;

use Corals\Modules\Gateway\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Books an issuer settlement payout: debits issuer_payable for the net
 * amount being paid out and credits suspense (the outbound SPEI clearing
 * leg), mirroring TopupAppliedPosting's inbound suspense leg. See
 * docs/settlement-reconciliation.md ("Settlement OUT to issuers") and
 * docs/data-model.md ("settlements" — "Draws down issuer_payable").
 *
 * Idempotent on settlement_id: a replay for a settlement that already has a
 * booked posting returns the existing posting_id instead of writing a
 * second one.
 */
class SettlementPayoutPosting
{
    public function apply(int $settlementId, int $issuerId, int $netCentavos): string
    {
        return DB::transaction(function () use ($settlementId, $issuerId, $netCentavos) {
            $existingPostingId = LedgerEntry::where('settlement_id', $settlementId)
                ->where('account_type', 'issuer_payable')
                ->value('posting_id');

            if ($existingPostingId !== null) {
                return $existingPostingId;
            }

            $postingId = (string) Str::uuid();

            $legs = [
                [
                    'posting_id' => $postingId,
                    'account_type' => 'issuer_payable',
                    'account_ref' => (string) $issuerId,
                    'direction' => 'debit',
                    'amount_centavos' => $netCentavos,
                    'settlement_id' => $settlementId,
                    'created_at' => now(),
                ],
                [
                    'posting_id' => $postingId,
                    'account_type' => 'suspense',
                    'account_ref' => 'spei_outbound',
                    'direction' => 'credit',
                    'amount_centavos' => $netCentavos,
                    'settlement_id' => $settlementId,
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
