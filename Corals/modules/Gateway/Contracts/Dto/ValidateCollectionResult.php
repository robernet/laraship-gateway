<?php

namespace Corals\Modules\Gateway\Contracts\Dto;

/**
 * core -> adapter reply (contracts/asyncapi.yaml "ValidateCollectionResult").
 * Built by Contracts\Drivers\InProcessDriver via fromArray() from
 * Core\Collections\ValidateCollection::handle()'s array shape, and unpacked
 * by Adapters/* via toArray() back into the network's native response shape.
 */
final class ValidateCollectionResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $intentState,
        public readonly ?array $amountPolicy,
        public readonly ?string $reservationId,
        public readonly ?string $declineReason,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ok: $data['ok'],
            intentState: $data['intent_state'] ?? null,
            amountPolicy: $data['amount_policy'] ?? null,
            reservationId: $data['reservation_id'] ?? null,
            declineReason: $data['decline_reason'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'intent_state' => $this->intentState,
            'amount_policy' => $this->amountPolicy,
            'reservation_id' => $this->reservationId,
            'decline_reason' => $this->declineReason,
        ];
    }
}
