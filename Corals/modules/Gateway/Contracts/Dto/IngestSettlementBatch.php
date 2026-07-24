<?php

namespace Corals\Modules\Gateway\Contracts\Dto;

/**
 * adapter -> core command for SFTP-batch networks
 * (contracts/asyncapi.yaml "IngestSettlementBatchRequest",
 * docs/adapter-contract.md "Direction A"). Rows keep the same plain-array
 * shape Core\Collections\IngestSettlementBatch::handle() already expects
 * (network_txn_id, mid, ref, amount_paid, collected_at) — a per-row value
 * object isn't warranted while the only caller is this DTO's toArray().
 */
final class IngestSettlementBatch
{
    public function __construct(
        public readonly int $contractV,
        public readonly string $networkId,
        public readonly string $batchId,
        public readonly array $rows,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            contractV: $data['contract_v'],
            networkId: $data['network_id'],
            batchId: $data['batch_id'],
            rows: $data['rows'],
        );
    }

    public function toArray(): array
    {
        return [
            'contract_v' => $this->contractV,
            'network_id' => $this->networkId,
            'batch_id' => $this->batchId,
            'rows' => $this->rows,
        ];
    }
}
