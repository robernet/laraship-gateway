<?php

namespace Corals\Modules\Gateway\Contracts\Dto;

/**
 * adapter -> core command (contracts/asyncapi.yaml "ValidateCollectionRequest",
 * docs/adapter-contract.md "Direction A"). Money = integer centavos. Built by
 * an Adapters/* class from a network's native payload via fromArray(), and
 * unpacked by Contracts\Drivers\InProcessDriver via toArray() into the exact
 * shape Core\Collections\ValidateCollection::handle() already expects.
 */
final class ValidateCollection
{
    public function __construct(
        public readonly int $contractV,
        public readonly string $networkId,
        public readonly string $mid,
        public readonly string $ref,
        public readonly int $amountAttempt,
        public readonly string $storeId,
        public readonly string $terminalId,
        public readonly string $requestId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            contractV: $data['contract_v'],
            networkId: $data['network_id'],
            mid: $data['mid'],
            ref: $data['ref'],
            amountAttempt: $data['amount_attempt'],
            storeId: $data['store_id'],
            terminalId: $data['terminal_id'],
            requestId: $data['request_id'],
        );
    }

    public function toArray(): array
    {
        return [
            'contract_v' => $this->contractV,
            'network_id' => $this->networkId,
            'mid' => $this->mid,
            'ref' => $this->ref,
            'amount_attempt' => $this->amountAttempt,
            'store_id' => $this->storeId,
            'terminal_id' => $this->terminalId,
            'request_id' => $this->requestId,
        ];
    }
}
