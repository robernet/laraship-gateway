<?php

namespace Corals\Modules\Gateway\Core\Intents;

use Corals\Modules\Gateway\Models\IdempotencyKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Generic Idempotency-Key handling for mutating Issuer-API endpoints
 * (module CLAUDE.md: "Idempotency + replay live in Core, not adapters").
 * Same key + same request body -> stored response replayed, no second
 * effect. Same key + a different body -> rejected (key reuse is a caller
 * bug, not a valid replay).
 *
 * ponytail: concurrency ceiling is the idempotency_keys(scope,key) unique
 * constraint — two truly concurrent first-attempts both run $operation and
 * the loser's INSERT fails with a QueryException instead of replaying.
 * Fine for v1 issuer traffic; revisit if that becomes a real race.
 */
class IdempotentRequest
{
    /**
     * @return array{status: int, body: array, replayed: bool}
     */
    public function handle(string $scope, string $key, array $requestPayload, int $ttlSeconds, callable $operation): array
    {
        $requestHash = hash('sha256', json_encode($requestPayload));

        $existing = IdempotencyKey::where('scope', $scope)->where('key', $key)->first();

        if ($existing) {
            if ($existing->request_hash !== $requestHash) {
                throw ValidationException::withMessages([
                    'Idempotency-Key' => ['This Idempotency-Key was already used with a different request body.'],
                ]);
            }

            return ['status' => $existing->response_status, 'body' => $existing->response_snapshot, 'replayed' => true];
        }

        return DB::transaction(function () use ($scope, $key, $requestHash, $ttlSeconds, $operation) {
            [$status, $body] = $operation();

            IdempotencyKey::create([
                'scope' => $scope,
                'key' => $key,
                'request_hash' => $requestHash,
                'response_status' => $status,
                'response_snapshot' => $body,
                'expires_at' => now()->addSeconds($ttlSeconds),
            ]);

            return ['status' => $status, 'body' => $body, 'replayed' => false];
        });
    }
}
