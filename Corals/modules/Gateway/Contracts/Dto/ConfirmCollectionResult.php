<?php

namespace Corals\Modules\Gateway\Contracts\Dto;

/**
 * core -> adapter reply (contracts/asyncapi.yaml "ConfirmCollectionResult").
 * Built by Contracts\Drivers\InProcessDriver via fromArray() from
 * Core\Collections\ConfirmCollection::handle()'s array shape, and unpacked
 * by Adapters/* via toArray() back into the network's native response shape.
 */
final class ConfirmCollectionResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $transactionPublicId,
        public readonly ?string $authCode,
        public readonly ?array $receipt,
        public readonly ?string $error,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ok: $data['ok'],
            transactionPublicId: $data['transaction_public_id'] ?? null,
            authCode: $data['auth_code'] ?? null,
            receipt: $data['receipt'] ?? null,
            error: $data['error'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'transaction_public_id' => $this->transactionPublicId,
            'auth_code' => $this->authCode,
            'receipt' => $this->receipt,
            'error' => $this->error,
        ];
    }
}
