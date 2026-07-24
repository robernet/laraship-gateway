<?php

namespace Corals\Modules\Gateway\Contracts\Dto;

/**
 * core -> adapter reply for a settlement batch. contracts/asyncapi.yaml's
 * onIngestSettlementBatch operation declares no reply channel (row-level
 * failures land in reconciliation_exceptions rather than a synchronous
 * response, docs/adapter-contract.md "IngestSettlementBatch"), so this shape
 * mirrors the summary Core\Collections\IngestSettlementBatch::handle()
 * already returns (batch_id, confirmed_count, exception_count, exceptions)
 * rather than guessing at an unspecified schema.
 */
final class IngestSettlementBatchResult
{
    public function __construct(
        public readonly string $batchId,
        public readonly int $confirmedCount,
        public readonly int $exceptionCount,
        public readonly array $exceptions,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            batchId: $data['batch_id'],
            confirmedCount: $data['confirmed_count'],
            exceptionCount: $data['exception_count'],
            exceptions: $data['exceptions'],
        );
    }

    public function toArray(): array
    {
        return [
            'batch_id' => $this->batchId,
            'confirmed_count' => $this->confirmedCount,
            'exception_count' => $this->exceptionCount,
            'exceptions' => $this->exceptions,
        ];
    }
}
