<?php

namespace Corals\Modules\Gateway\Core\References;

use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\Models\PaymentReference;
use InvalidArgumentException;

/**
 * Generates the reference token + human_reference for a PaymentIntent under
 * either mapping strategy. See docs/reference-qr-spec.md ("Invoice mapping").
 *
 * Deterministic: token = HMAC-SHA256(issuer.reference_secret, invoice_id|merchant_id|salt)
 * mod 1e10, regenerable (no stored map). A salt counter absorbs the rare
 * mod-1e10 collision without ever storing invoice_id -> token.
 *
 * Stored: a random 10-digit token persisted on payment_references (the
 * invoice_id -> token map is the row itself).
 */
class ReferenceGenerator
{
    public function generate(PaymentIntent $intent): PaymentReference
    {
        $token = match ($intent->mapping_strategy) {
            'deterministic' => $this->deterministicToken($intent),
            'stored' => $this->randomToken(),
            default => throw new InvalidArgumentException("Unknown mapping_strategy [{$intent->mapping_strategy}]."),
        };

        return PaymentReference::create([
            'payment_intent_id' => $intent->id,
            'reference_token' => $token,
            'human_reference' => $token.Mod97Check::compute($token),
            'expires_at' => $intent->expires_at,
        ]);
    }

    private function deterministicToken(PaymentIntent $intent): string
    {
        $secret = $intent->issuer->reference_secret;

        for ($salt = 0; $salt < 100; $salt++) {
            $token = $this->derive($intent->invoice_id, $intent->merchant_id, $secret, $salt);

            if (! PaymentReference::where('reference_token', $token)->exists()) {
                return $token;
            }
        }

        throw new InvalidArgumentException("Could not derive a unique deterministic token for invoice [{$intent->invoice_id}].");
    }

    private function derive(string $invoiceId, int $merchantId, string $secret, int $salt): string
    {
        $hash = hash_hmac('sha256', "{$invoiceId}|{$merchantId}|{$salt}", $secret);

        return str_pad((string) (hexdec(substr($hash, 0, 15)) % 10_000_000_000), 10, '0', STR_PAD_LEFT);
    }

    private function randomToken(): string
    {
        do {
            $token = str_pad((string) random_int(0, 9_999_999_999), 10, '0', STR_PAD_LEFT);
        } while (PaymentReference::where('reference_token', $token)->exists());

        return $token;
    }
}
