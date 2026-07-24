<?php

namespace Corals\Modules\Gateway\Contracts;

use Corals\Modules\Gateway\Contracts\Dto\ConfirmCollection;
use Corals\Modules\Gateway\Contracts\Dto\ConfirmCollectionResult;
use Corals\Modules\Gateway\Contracts\Dto\IngestSettlementBatch;
use Corals\Modules\Gateway\Contracts\Dto\IngestSettlementBatchResult;
use Corals\Modules\Gateway\Contracts\Dto\ValidateCollection;
use Corals\Modules\Gateway\Contracts\Dto\ValidateCollectionResult;

/**
 * THE migration boundary (docs/adapter-contract.md "Transport"). Core codes
 * against this interface, never a concrete driver. Phase 1 binds
 * Drivers\InProcessDriver (in-process call into Core\Collections\*); Phase 2
 * swaps in Drivers\RemoteHttpDriver (HTTP/queue to the extracted NestJS
 * service) with zero core rewrite. See Corals/modules/Gateway/CLAUDE.md for
 * invariants.
 */
interface AdapterGateway
{
    public function validateCollection(ValidateCollection $command): ValidateCollectionResult;

    public function confirmCollection(ConfirmCollection $command): ConfirmCollectionResult;

    public function ingestSettlementBatch(IngestSettlementBatch $command): IngestSettlementBatchResult;
}
