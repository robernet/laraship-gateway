<?php

namespace Corals\Modules\Gateway\Core\Collections;

use Corals\Modules\Gateway\Core\Audit\AuditLogger;
use Corals\Modules\Gateway\Core\Ledger\Postings\VoidReversalPosting;
use Corals\Modules\Gateway\Models\LedgerEntry;
use Corals\Modules\Gateway\Models\Transaction;
use Corals\Modules\Gateway\Models\VoidRequest;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * GW-505 (docs/state-machines.md "CONFIRMED → VOIDED (void window only)",
 * docs/security-antifraud.md "Void collusion"). Only handles the CONFIRMED
 * (non-FINALIZED) correction path — a FINALIZED transaction is out of scope
 * here by design ("blocked once FINALIZED unless routed through
 * reconciliation_exceptions"), which GW-504's
 * ReconciliationExceptionWorkflow::resolve() already covers via
 * VoidReversalPosting's own exceptions-path override.
 *
 * request() is the single entry point: below the dual-control threshold it
 * finalizes immediately (a single actor is enough); at/above threshold it
 * only opens a pending void_requests row — money never moves until
 * approve() is called by someone other than the requester. That "someone
 * else" check is the actual enforcement of "single-actor void above
 * threshold is impossible", not a UI-level nicety.
 */
class VoidCollection
{
    public function request(int $transactionId, string $requestedBy, ?string $reason = null): VoidRequest
    {
        return DB::transaction(function () use ($transactionId, $requestedBy, $reason) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transactionId);

            if ($transaction->state !== 'CONFIRMED') {
                throw new RuntimeException(
                    "Transaction {$transactionId} is not voidable from state {$transaction->state}."
                );
            }

            $windowSeconds = (int) config('gateway.void_window_seconds');

            if (! $transaction->confirmed_at || $transaction->confirmed_at->addSeconds($windowSeconds)->isPast()) {
                throw new RuntimeException("Transaction {$transactionId} is outside its void window.");
            }

            $postingId = LedgerEntry::where('transaction_id', $transactionId)
                ->where('account_type', 'pos_wallet')
                ->value('posting_id');

            if ($postingId === null) {
                throw new RuntimeException("No posting found for transaction {$transactionId}.");
            }

            $voidRequest = VoidRequest::create([
                'transaction_id' => $transactionId,
                'posting_id' => $postingId,
                'amount_centavos' => $transaction->amount_centavos,
                'requested_by' => $requestedBy,
                'status' => 'pending',
                'reason' => $reason,
            ]);

            (new AuditLogger())->record($requestedBy, 'void.requested', 'transaction', (string) $transactionId, [
                'void_request_id' => $voidRequest->id,
                'amount_centavos' => $transaction->amount_centavos,
            ]);

            $threshold = (int) config('gateway.void_dual_control_threshold_centavos');

            if ($transaction->amount_centavos < $threshold) {
                return $this->finalize($voidRequest, $requestedBy);
            }

            return $voidRequest;
        });
    }

    public function approve(int $voidRequestId, string $approvedBy): VoidRequest
    {
        return DB::transaction(function () use ($voidRequestId, $approvedBy) {
            $voidRequest = VoidRequest::lockForUpdate()->findOrFail($voidRequestId);

            if ($voidRequest->status !== 'pending') {
                throw new RuntimeException("Void request {$voidRequestId} is not pending.");
            }

            if ($approvedBy === $voidRequest->requested_by) {
                throw new RuntimeException('A void at or above the dual-control threshold requires a second, distinct approver.');
            }

            return $this->finalize($voidRequest, $approvedBy);
        });
    }

    private function finalize(VoidRequest $voidRequest, string $approvedBy): VoidRequest
    {
        $voidPostingId = (new VoidReversalPosting())->apply($voidRequest->posting_id, false);

        Transaction::where('id', $voidRequest->transaction_id)->update(['state' => 'VOIDED']);

        $voidRequest->update([
            'status' => 'voided',
            'approved_by' => $approvedBy,
            'voided_posting_id' => $voidPostingId,
        ]);

        (new AuditLogger())->record($approvedBy, 'void.completed', 'transaction', (string) $voidRequest->transaction_id, [
            'void_request_id' => $voidRequest->id,
            'voided_posting_id' => $voidPostingId,
        ]);

        return $voidRequest;
    }
}
