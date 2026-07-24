<?php

namespace Corals\Modules\Gateway\Core\Settlement;

use Corals\Modules\Gateway\Models\ReconciliationException;
use Corals\Modules\Gateway\Models\Transaction;

/**
 * GW-503 (docs/settlement-reconciliation.md "Network settlement
 * reconciliation"). Matches a network's remittance report — one row per
 * network_txn_id with the amount the network says it paid — against
 * transactions already booked by confirm/batch-confirm (GW-402/GW-403).
 * Real-time/webhook networks reconcile confirms this way; batch networks
 * reconcile the same rows against the totals IngestSettlementBatch already
 * booked ("matching reconciles file totals vs booked totals" per the docs).
 *
 * Amounts may differ by up to `gateway.reconciliation_tolerance_centavos`
 * (rounding/FX noise from the network side); anything wider opens a typed
 * `amount_mismatch` exception rather than being silently accepted or
 * silently dropped. A network_txn_id repeated within the same remittance
 * report is a duplicate remittance row, not a second confirm.
 */
class NetworkReconciliation
{
    public function reconcile(string $networkId, array $rows): array
    {
        $tolerance = (int) config('gateway.reconciliation_tolerance_centavos', 0);
        $matchedCount = 0;
        $exceptions = [];
        $seen = [];

        foreach ($rows as $row) {
            if (isset($seen[$row['network_txn_id']])) {
                $exceptions[] = $this->recordException($networkId, $row, 'duplicate');
                continue;
            }
            $seen[$row['network_txn_id']] = true;

            $transaction = Transaction::where('network_id', $networkId)
                ->where('network_txn_id', $row['network_txn_id'])
                ->whereIn('state', ['CONFIRMED', 'FINALIZED'])
                ->first();

            if (! $transaction) {
                $exceptions[] = $this->recordException($networkId, $row, 'unmatched_confirm');
                continue;
            }

            if (abs($transaction->amount_centavos - $row['amount_paid']) > $tolerance) {
                $exceptions[] = $this->recordException($networkId, $row, 'amount_mismatch');
                continue;
            }

            $matchedCount++;
        }

        return [
            'network_id' => $networkId,
            'matched_count' => $matchedCount,
            'exception_count' => count($exceptions),
            'exceptions' => $exceptions,
        ];
    }

    private function recordException(string $networkId, array $row, string $type): array
    {
        ReconciliationException::create([
            'type' => $type,
            'refs' => [
                'network_id' => $networkId,
                'network_txn_id' => $row['network_txn_id'],
                'amount_paid' => $row['amount_paid'],
            ],
        ]);

        return ['network_txn_id' => $row['network_txn_id'], 'type' => $type];
    }
}
