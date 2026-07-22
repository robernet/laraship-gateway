<?php

namespace Corals\Modules\Gateway\Core\References;

use Corals\Modules\Gateway\Models\MerchantKey;
use Corals\Modules\Gateway\Models\PaymentReference;
use InvalidArgumentException;

/**
 * Verifies a QR payload per docs/reference-qr-spec.md: "Authenticity is
 * enforced server-side, always ... A valid-looking QR with a bad server
 * check is rejected regardless of signature." Signature/exp/mid are
 * necessary but never sufficient — the money decision (does
 * $attemptedAmountCentavos belong to this intent) is always re-derived from
 * the current DB row, never from the payload's own `amt`. The nonce is
 * checked against a Redis replay cache (GW-206): a second sighting within
 * the instrument's own validity window is rejected outright.
 */
class QrVerifier
{
    public function __construct(private readonly ReplayCache $replayCache = new ReplayCache()) {}

    public function verify(string $payloadJson, int $attemptedAmountCentavos): PaymentReference
    {
        $data = json_decode($payloadJson, true);

        if (! is_array($data) || ! isset($data['sig'], $data['kid'], $data['ref'], $data['exp'], $data['mid'], $data['non'])) {
            throw new InvalidArgumentException('Malformed QR payload.');
        }

        $signature = $data['sig'];
        $signedFields = $data;
        unset($signedFields['sig']);

        $key = MerchantKey::where('kid', $data['kid'])->whereIn('state', ['active', 'retiring'])->first();

        if (! $key) {
            throw new InvalidArgumentException('Unknown or inactive signing key.');
        }

        // Dual-verify during the GW-205 rotation overlap: a `retiring` key
        // keeps verifying up to its own retire_after, then stops.
        if ($key->state === 'retiring' && $key->retire_after !== null && $key->retire_after->isPast()) {
            throw new InvalidArgumentException('Signing key has been retired.');
        }

        $expected = hash_hmac(
            'sha256',
            json_encode($signedFields, JSON_UNESCAPED_SLASHES),
            MockKeyVault::resolve($key->secret_ref)
        );

        if (! hash_equals($expected, $signature)) {
            throw new InvalidArgumentException('Invalid QR signature.');
        }

        if ($data['exp'] < now()->timestamp) {
            throw new InvalidArgumentException('QR payload has expired.');
        }

        if (! $this->replayCache->markIfNew($data['non'], $data['exp'] - now()->timestamp)) {
            throw new InvalidArgumentException('QR payload nonce has already been used.');
        }

        $reference = PaymentReference::where('reference_token', $data['ref'])->first();

        if (! $reference || $reference->status !== 'active') {
            throw new InvalidArgumentException('Reference is not active.');
        }

        $intent = $reference->paymentIntent;

        if ($data['mid'] !== $intent->merchant->mid) {
            throw new InvalidArgumentException('Payload mid does not match the reference on record.');
        }

        // The payload's own `amt` is never consulted below: the money
        // decision always comes from the DB row, fetched just now.
        $this->assertAmountMatchesDb($intent->amount_policy, $attemptedAmountCentavos);

        return $reference;
    }

    private function assertAmountMatchesDb(array $policy, int $attemptedAmountCentavos): void
    {
        if ($policy['type'] === 'fixed' && $attemptedAmountCentavos !== $policy['amount']) {
            throw new InvalidArgumentException('Attempted amount does not match the intent amount on record.');
        }

        if ($policy['type'] === 'variable'
            && ($attemptedAmountCentavos < $policy['min'] || $attemptedAmountCentavos > $policy['max'])) {
            throw new InvalidArgumentException('Attempted amount is outside the intent amount range on record.');
        }
    }
}
