<?php

namespace Corals\Modules\Gateway\Adapters\MockRealtime;

use Corals\Modules\Gateway\Contracts\AdapterGateway;
use Corals\Modules\Gateway\Contracts\Drivers\InProcessDriver;
use Corals\Modules\Gateway\Contracts\Dto\ConfirmCollection;
use Corals\Modules\Gateway\Contracts\Dto\ValidateCollection;

/**
 * `realtime` archetype (docs/adapter-contract.md "Direction A"). Mock network
 * `mock-realtime` speaks the contract shape natively — no field translation —
 * so this adapter's only job is fromArray()/toArray() at the boundary and,
 * per the strangler seam (Corals/modules/Gateway/CLAUDE.md), importing
 * ONLY Contracts\*, never Core/Models/Http directly.
 *
 * simulateTimeout reproduces a network that never responds: it throws BEFORE
 * the AdapterGateway is called, so no Core state is ever touched — same as a
 * real timeout, leaving the caller to retry with the same
 * idempotency_key/network_txn_id.
 */
final class MockRealtimeAdapter
{
    public function __construct(
        private readonly AdapterGateway $gateway = new InProcessDriver(),
        private readonly bool $simulateTimeout = false,
    ) {
    }

    public function validate(array $nativePayload): array
    {
        if ($this->simulateTimeout) {
            throw new MockRealtimeTimeoutException('mock-realtime: validateCollection timed out');
        }

        return $this->gateway->validateCollection(ValidateCollection::fromArray($nativePayload))->toArray();
    }

    public function confirm(array $nativePayload): array
    {
        if ($this->simulateTimeout) {
            throw new MockRealtimeTimeoutException('mock-realtime: confirmCollection timed out');
        }

        return $this->gateway->confirmCollection(ConfirmCollection::fromArray($nativePayload))->toArray();
    }
}
