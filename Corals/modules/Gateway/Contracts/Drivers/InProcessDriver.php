<?php

namespace Corals\Modules\Gateway\Contracts\Drivers;

use Corals\Modules\Gateway\Contracts\AdapterGateway;
use Corals\Modules\Gateway\Contracts\Dto\ConfirmCollection as ConfirmCollectionCommand;
use Corals\Modules\Gateway\Contracts\Dto\ConfirmCollectionResult;
use Corals\Modules\Gateway\Contracts\Dto\IngestSettlementBatch as IngestSettlementBatchCommand;
use Corals\Modules\Gateway\Contracts\Dto\IngestSettlementBatchResult;
use Corals\Modules\Gateway\Contracts\Dto\ValidateCollection as ValidateCollectionCommand;
use Corals\Modules\Gateway\Contracts\Dto\ValidateCollectionResult;
use Corals\Modules\Gateway\Core\Collections\ConfirmCollection;
use Corals\Modules\Gateway\Core\Collections\IngestSettlementBatch;
use Corals\Modules\Gateway\Core\Collections\ValidateCollection;

/**
 * Phase 1 transport (docs/adapter-contract.md "Transport" table): bridges
 * Contracts DTOs directly, in-process, to the already-shipped
 * Core\Collections\* array-based services (GW-401/402/403) — translating
 * DTO <-> array at the boundary so those classes' handle(array): array
 * signatures are untouched.
 *
 * This is the ONLY place Contracts is allowed to reach into Core
 * (depfile.yaml "Contracts: [Core]") — Adapters/* still see ONLY Contracts,
 * never Core directly, so the strangler-seam invariant holds. Phase 2 swaps
 * this class for Drivers\RemoteHttpDriver with zero change to Core or the
 * DTOs (docs/adapter-contract.md "Transport").
 */
final class InProcessDriver implements AdapterGateway
{
    public function validateCollection(ValidateCollectionCommand $command): ValidateCollectionResult
    {
        return ValidateCollectionResult::fromArray((new ValidateCollection())->handle($command->toArray()));
    }

    public function confirmCollection(ConfirmCollectionCommand $command): ConfirmCollectionResult
    {
        return ConfirmCollectionResult::fromArray((new ConfirmCollection())->handle($command->toArray()));
    }

    public function ingestSettlementBatch(IngestSettlementBatchCommand $command): IngestSettlementBatchResult
    {
        return IngestSettlementBatchResult::fromArray((new IngestSettlementBatch())->handle($command->toArray()));
    }
}
