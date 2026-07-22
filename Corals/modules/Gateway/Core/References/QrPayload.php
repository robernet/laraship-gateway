<?php

namespace Corals\Modules\Gateway\Core\References;

use Corals\Modules\Gateway\Models\MerchantKey;
use Corals\Modules\Gateway\Models\PaymentReference;
use InvalidArgumentException;

/**
 * Builds and signs the QR payload for a PaymentReference. See
 * docs/reference-qr-spec.md ("QR payload"). `sig` is an HMAC-SHA256 over the
 * canonical (insertion-order) JSON serialization of every other field.
 */
class QrPayload
{
    private const CURRENCY = 'MXN';

    public static function issue(PaymentReference $reference, MerchantKey $key, int $ttlSeconds = 900): PaymentReference
    {
        $intent = $reference->paymentIntent;

        $fields = [
            'v' => 1,
            'mid' => $intent->merchant->mid,
            'ref' => $reference->reference_token,
            'amt' => self::amountFrom($intent->amount_policy),
            'exp' => $reference->expires_at?->timestamp ?? now()->addSeconds($ttlSeconds)->timestamp,
            'non' => bin2hex(random_bytes(16)),
            'kid' => $key->kid,
        ];

        $fields['sig'] = hash_hmac(
            'sha256',
            json_encode($fields, JSON_UNESCAPED_SLASHES),
            MockKeyVault::resolve($key->secret_ref)
        );

        $reference->update([
            'qr_payload' => json_encode($fields, JSON_UNESCAPED_SLASHES),
            'kid' => $key->kid,
            'nonce' => $fields['non'],
        ]);

        return $reference->fresh();
    }

    private static function amountFrom(array $policy): array
    {
        return match ($policy['type']) {
            'fixed' => [
                'cur' => self::CURRENCY,
                'type' => 'fixed',
                'amount' => $policy['amount'],
            ],
            'variable' => [
                'cur' => self::CURRENCY,
                'type' => 'variable',
                'min' => $policy['min'],
                'max' => $policy['max'],
                'partial' => $policy['allow_partial'] ?? false,
            ],
            default => throw new InvalidArgumentException("Unknown amount_policy type [{$policy['type']}]."),
        };
    }
}
