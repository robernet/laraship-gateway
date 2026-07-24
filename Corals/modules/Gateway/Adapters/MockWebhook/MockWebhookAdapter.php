<?php

namespace Corals\Modules\Gateway\Adapters\MockWebhook;

use Corals\Modules\Gateway\Contracts\AdapterGateway;
use Corals\Modules\Gateway\Contracts\Drivers\InProcessDriver;
use Corals\Modules\Gateway\Contracts\Dto\ConfirmCollection;
use Illuminate\Support\Facades\Cache;

/**
 * `webhook` archetype (docs/adapter-contract.md "Direction A"). Mock network
 * `mock-webhook` pushes an HMAC-signed payload with no prior validate call
 * (webhook networks confirm directly — see Core\Collections\ConfirmCollection's
 * "no prior reservation" branch); this adapter verifies the signature and
 * rejects replays BEFORE the AdapterGateway is ever called, per the
 * strangler seam (Corals/modules/Gateway/CLAUDE.md): imports ONLY Contracts\*,
 * never Core/Models/Http.
 *
 * Signature: hex HMAC-SHA256 over the payload's ksort'd JSON (excluding
 * `signature` itself), keyed by a shared secret provisioned per network_id.
 * Replay guard: `txn_id` is cached for $replayTtlSeconds after first
 * acceptance — a second signed push with the same id is rejected here, as
 * defense-in-depth ahead of (not a replacement for) Core's own
 * network_txn_id idempotency check.
 */
final class MockWebhookAdapter
{
    public function __construct(
        private readonly string $sharedSecret,
        private readonly AdapterGateway $gateway = new InProcessDriver(),
        private readonly int $replayTtlSeconds = 86400,
    ) {
    }

    public function receive(array $payload): array
    {
        $signature = $payload['signature'] ?? '';

        if ($signature === '' || ! hash_equals($this->expectedSignature($payload), $signature)) {
            throw new MockWebhookSignatureException('mock-webhook: invalid or missing signature');
        }

        $replayKey = 'gateway:mock-webhook:seen:'.$payload['txn_id'];

        if (Cache::has($replayKey)) {
            throw new MockWebhookReplayException("mock-webhook: {$payload['txn_id']} already delivered");
        }

        Cache::put($replayKey, true, $this->replayTtlSeconds);

        return $this->gateway->confirmCollection(ConfirmCollection::fromArray([
            'contract_v' => 1,
            'network_id' => 'mock-webhook',
            'mid' => $payload['mid'],
            'ref' => $payload['ref'],
            'amount_paid' => $payload['amount_paid'],
            'is_partial' => $payload['is_partial'] ?? false,
            'network_txn_id' => $payload['txn_id'],
            'idempotency_key' => $payload['idempotency_key'] ?? $payload['txn_id'],
            'store_id' => $payload['store_id'],
            'terminal_id' => $payload['terminal_id'],
            'collected_at' => $payload['collected_at'],
        ]))->toArray();
    }

    private function expectedSignature(array $payload): string
    {
        $fields = $payload;
        unset($fields['signature']);
        ksort($fields);

        return hash_hmac('sha256', json_encode($fields), $this->sharedSecret);
    }
}
