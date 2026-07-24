<?php

namespace Corals\Modules\Gateway\Core\Exceptions;

use Corals\Modules\Gateway\Core\Ledger\Postings\VoidReversalPosting;
use Corals\Modules\Gateway\Models\ReconciliationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * GW-504 (BACKLOG.md "Exceptions queue + case workflow (admin)"). The three
 * verbs an admin case worker performs on a reconciliation_exceptions row:
 * assign, investigate, resolve.
 *
 * Money only ever moves on resolve(), and only via the exceptions-path
 * escape hatch VoidReversalPosting already exposes for GW-505's void flow
 * (docs/settlement-reconciliation.md: "Post-FINALIZED corrections only via
 * the exceptions queue with an explicit adjustment posting") — reusing the
 * existing balanced-reversal code path rather than inventing a second one.
 * The caller supplies the posting_id to reverse (found during
 * investigation); this class never derives it from the exception's `refs`,
 * since refs shape varies by exception type and some types (unmatched_confirm)
 * have no booked transaction to derive a posting from at all.
 */
class ReconciliationExceptionWorkflow
{
    public function assign(int $exceptionId, string $assignee): ReconciliationException
    {
        $exception = ReconciliationException::findOrFail($exceptionId);
        $exception->update(['assignee' => $assignee]);

        return $exception;
    }

    public function investigate(int $exceptionId): ReconciliationException
    {
        $exception = ReconciliationException::findOrFail($exceptionId);

        if ($exception->state !== 'open') {
            throw new RuntimeException("Exception {$exceptionId} is not open (state: {$exception->state}).");
        }

        $exception->update(['state' => 'investigating']);

        return $exception;
    }

    public function resolve(int $exceptionId, string $resolution, ?string $reversePostingId = null): array
    {
        return DB::transaction(function () use ($exceptionId, $resolution, $reversePostingId) {
            $exception = ReconciliationException::lockForUpdate()->findOrFail($exceptionId);

            if ($exception->state === 'resolved') {
                throw new RuntimeException("Exception {$exceptionId} is already resolved.");
            }

            $correctivePostingId = null;

            if ($reversePostingId !== null) {
                // isFinalized/viaExceptionsPath are both hardcoded true: the
                // exceptions queue is itself the override path, regardless
                // of the original posting's actual finality.
                $correctivePostingId = (new VoidReversalPosting())->apply($reversePostingId, true, true);
            }

            $exception->update([
                'state' => 'resolved',
                'resolution' => $resolution,
                'refs' => array_merge($exception->refs ?? [], array_filter([
                    'corrective_posting_id' => $correctivePostingId,
                ])),
            ]);

            return [
                'ok' => true,
                'exception_id' => $exception->id,
                'corrective_posting_id' => $correctivePostingId,
            ];
        });
    }
}
