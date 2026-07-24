<?php

namespace Corals\Modules\Gateway\Contracts\Dto;

/**
 * adapter -> core command (contracts/asyncapi.yaml "ConfirmCollectionRequest",
 * docs/adapter-contract.md "Direction A"). Carries network_txn_id +
 * idempotency_key — Core's idempotency/replay guarantee depends on these
 * passing through unchanged on every retry. Money = integer centavos.
 */
final class ConfirmCollection
{
    public function __construct(
        public readonly int $contractV,
        public readonly string $networkId,
        public readonly string $mid,
        public readonly string $ref,
        public readonly int $amountPaid,
        public readonly bool $isPartial,
        public readonly string $networkTxnId,
        public readonly string $idempotencyKey,
        public readonly string $storeId,
        public readonly string $terminalId,
        public readonly int $collectedAt,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            contractV: $data['contract_v'],
            networkId: $data['network_id'],
            mid: $data['mid'],
            ref: $data['ref'],
            amountPaid: $data['amount_paid'],
            isPartial: $data['is_partial'],
            networkTxnId: $data['network_txn_id'],
            idempotencyKey: $data['idempotency_key'],
            storeId: $data['store_id'],
            terminalId: $data['terminal_id'],
            collectedAt: $data['collected_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'contract_v' => $this->contractV,
            'network_id' => $this->networkId,
            'mid' => $this->mid,
            'ref' => $this->ref,
            'amount_paid' => $this->amountPaid,
            'is_partial' => $this->isPartial,
            'network_txn_id' => $this->networkTxnId,
            'idempotency_key' => $this->idempotencyKey,
            'store_id' => $this->storeId,
            'terminal_id' => $this->terminalId,
            'collected_at' => $this->collectedAt,
        ];
    }
}
