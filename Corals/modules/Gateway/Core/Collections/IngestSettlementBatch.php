<?php

namespace Corals\Modules\Gateway\Core\Collections;

use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\ReconciliationException;
use Throwable;

/**
 * POST /v1/cash/batch-confirm (GW-403, contracts/openapi.yaml
 * "IngestSettlementBatchRequest", docs/adapter-contract.md
 * "IngestSettlementBatch"). Fans each row of an SFTP-style settlement batch
 * into ConfirmCollection with finality=batch and no prior reservation —
 * batch/offline networks skip validate entirely, so the transaction begins
 * at confirm (docs/state-machines.md "finality note").
 *
 * Wallet resolution (the one genuine design gap in this ticket): batch rows
 * carry only network_id/mid/ref/amount_paid/collected_at — no store_id,
 * because settlement files settle at the network level, not per store, and
 * the schema has no FK from Merchant to PosWallet (see Models/Merchant.php,
 * Models/PosWallet.php — a wallet is keyed by network_id + external_store_id
 * only). Rather than inventing a new required field the contract doesn't
 * have, a row resolves to a wallet only when exactly one active PosWallet
 * exists for the batch's network_id; zero or multiple active wallets for
 * that network makes the row unresolvable and it is recorded as an
 * unmatched_confirm reconciliation exception, never a hard failure.
 *
 * Row-level failures — whether a normal ConfirmCollection decline
 * (ok=>false, e.g. mid/reference not found, amount mismatch, duplicate) or a
 * failure before ConfirmCollection is even reached (no resolvable wallet, or
 * an unexpected exception) — land in reconciliation_exceptions and never
 * abort the batch (ticket's Done-when criterion).
 */
class IngestSettlementBatch
{
    public function handle(array $data): array
    {
        $confirmedCount = 0;
        $exceptions = [];

        foreach ($data['rows'] as $row) {
            try {
                $result = $this->processRow($data['network_id'], $data['batch_id'], $row);
            } catch (Throwable $e) {
                $result = ['ok' => false, 'error' => 'processing_error'];
            }

            if ($result['ok']) {
                $confirmedCount++;
            } else {
                $exceptions[] = $this->recordException($row, $result['error']);
            }
        }

        return [
            'batch_id' => $data['batch_id'],
            'confirmed_count' => $confirmedCount,
            'exception_count' => count($exceptions),
            'exceptions' => $exceptions,
        ];
    }

    private function processRow(string $networkId, string $batchId, array $row): array
    {
        $wallets = PosWallet::where('network_id', $networkId)->where('status', 'active')->get();

        if ($wallets->count() !== 1) {
            return ['ok' => false, 'error' => 'wallet_not_found'];
        }

        return (new ConfirmCollection())->handle([
            'contract_v' => 1,
            'network_id' => $networkId,
            'mid' => $row['mid'],
            'ref' => $row['ref'],
            'amount_paid' => $row['amount_paid'],
            'is_partial' => false,
            'network_txn_id' => $row['network_txn_id'],
            'idempotency_key' => $batchId.':'.$row['network_txn_id'],
            'store_id' => $wallets->first()->external_store_id,
            'collected_at' => $row['collected_at'],
            'finality' => 'batch',
        ]);
    }

    private function recordException(array $row, string $error): array
    {
        $type = match ($error) {
            'mid_not_found', 'reference_not_found', 'wallet_not_found' => 'unmatched_confirm',
            'amount_mismatch' => 'amount_mismatch',
            'reference_already_consumed' => 'duplicate',
            default => 'unmatched_confirm',
        };

        ReconciliationException::create([
            'type' => $type,
            'refs' => ['network_txn_id' => $row['network_txn_id']],
        ]);

        return [
            'network_txn_id' => $row['network_txn_id'],
            'type' => $type,
            'reason' => $error,
        ];
    }
}
