<?php

namespace Corals\Modules\Gateway\Core\Settlement;

use Corals\Modules\Gateway\Core\Ledger\Postings\SettlementPayoutPosting;
use Corals\Modules\Gateway\Models\LedgerEntry;
use Corals\Modules\Gateway\Models\OutboxEvent;
use Corals\Modules\Gateway\Models\Settlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * GW-502 (docs/settlement-reconciliation.md "Settlement OUT to issuers").
 * Aggregates the issuer's outstanding issuer_payable — every issuer_payable
 * credit not yet offset by a prior settlement's debit leg — into one
 * settlements row, books the payout posting that draws it down, and fires
 * `settlement.completed`. The mock SPEI payout is a generated reference; a
 * real bank/PSP rail is a later ticket (roadmap "Where you'll need help").
 *
 * The watermark for "already settled" is the highest ledger_entries.id among
 * this issuer's prior payout (debit) legs, not a timestamp: id is a
 * monotonically increasing PK, so it can't tie the way a same-second
 * `created_at` could when a payout and the next collection land in the same
 * clock second.
 */
class IssuerSettlement
{
    public function settle(int $issuerId, string $period): array
    {
        return DB::transaction(function () use ($issuerId, $period) {
            $lastPayoutId = LedgerEntry::where('account_type', 'issuer_payable')
                ->where('account_ref', (string) $issuerId)
                ->where('direction', 'debit')
                ->max('id');

            $creditEntries = LedgerEntry::where('account_type', 'issuer_payable')
                ->where('account_ref', (string) $issuerId)
                ->where('direction', 'credit')
                ->when($lastPayoutId, fn ($q) => $q->where('id', '>', $lastPayoutId))
                ->get();

            $net = (int) $creditEntries->sum('amount_centavos');

            if ($net <= 0) {
                throw new RuntimeException("No unsettled issuer_payable for issuer {$issuerId}.");
            }

            $postingIds = $creditEntries->pluck('posting_id');

            $siblingLegs = LedgerEntry::whereIn('posting_id', $postingIds)->get();
            $commission = (int) $siblingLegs->where('account_type', 'network_commission')->sum('amount_centavos');
            $fee = (int) $siblingLegs->where('account_type', 'gateway_fee')->sum('amount_centavos');
            $gross = $net + $commission + $fee;

            $settlement = Settlement::create([
                'issuer_id' => $issuerId,
                'period' => $period,
                'gross_centavos' => $gross,
                'commission_centavos' => $commission,
                'fee_centavos' => $fee,
                'net_centavos' => $net,
                'status' => 'pending',
            ]);

            $postingId = (new SettlementPayoutPosting())->apply($settlement->id, $issuerId, $net);

            $speiRef = 'SPEI-OUT-'.Str::uuid();

            $settlement->update([
                'status' => 'completed',
                'spei_ref' => $speiRef,
                'reconciled_at' => now(),
            ]);

            OutboxEvent::create([
                'event' => 'settlement.completed',
                'payload' => [
                    'settlement_id' => $settlement->id,
                    'issuer_id' => $issuerId,
                    'posting_id' => $postingId,
                    'net_centavos' => $net,
                    'spei_ref' => $speiRef,
                ],
            ]);

            return [
                'ok' => true,
                'settlement_id' => $settlement->id,
                'posting_id' => $postingId,
                'net_centavos' => $net,
                'spei_ref' => $speiRef,
            ];
        });
    }
}
