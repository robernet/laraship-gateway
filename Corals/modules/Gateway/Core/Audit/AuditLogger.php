<?php

namespace Corals\Modules\Gateway\Core\Audit;

use Corals\Modules\Gateway\Models\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * Tamper-evident audit log: each row's `row_hash` = sha256(prev_hash ||
 * canonical(row)), chaining every row to the one before it. See
 * docs/security-antifraud.md ("RBAC & audit"). Call `record()` for every
 * privileged/money action; call `firstBrokenRowId()` to detect tampering —
 * a row edited or deleted at the SQL layer (bypassing the model's
 * append-only guard) no longer reproduces its stored `row_hash`, so the
 * chain breaks at that row.
 */
class AuditLogger
{
    public function record(string $actor, string $action, ?string $subjectType, ?string $subjectId, array $payload): AuditLog
    {
        return DB::transaction(function () use ($actor, $action, $subjectType, $subjectId, $payload) {
            $prevHash = AuditLog::orderByDesc('id')->value('row_hash');
            $createdAt = now()->format('Y-m-d H:i:s');

            $rowHash = $this->hash($prevHash, $actor, $action, $subjectType, $subjectId, $payload, $createdAt);

            return AuditLog::create([
                'prev_hash' => $prevHash,
                'row_hash' => $rowHash,
                'actor' => $actor,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'payload' => $payload,
                'created_at' => $createdAt,
            ]);
        });
    }

    /**
     * Walks the chain in id order and returns the id of the first row whose
     * stored `row_hash` (or `prev_hash` linkage) no longer matches what it
     * should be, or null if the whole chain is intact.
     */
    public function firstBrokenRowId(): ?int
    {
        $expectedPrevHash = null;

        foreach (AuditLog::orderBy('id')->get() as $row) {
            $expectedHash = $this->hash(
                $expectedPrevHash,
                $row->actor,
                $row->action,
                $row->subject_type,
                $row->subject_id,
                $row->payload ?? [],
                $row->created_at->format('Y-m-d H:i:s')
            );

            if ($row->prev_hash !== $expectedPrevHash || $row->row_hash !== $expectedHash) {
                return $row->id;
            }

            $expectedPrevHash = $row->row_hash;
        }

        return null;
    }

    private function hash(
        ?string $prevHash,
        string $actor,
        string $action,
        ?string $subjectType,
        ?string $subjectId,
        array $payload,
        string $createdAt
    ): string {
        $canonical = json_encode([
            'prev_hash' => $prevHash,
            'actor' => $actor,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload' => $payload,
            'created_at' => $createdAt,
        ]);

        return hash('sha256', $canonical);
    }
}
