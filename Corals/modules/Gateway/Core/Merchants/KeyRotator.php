<?php

namespace Corals\Modules\Gateway\Core\Merchants;

use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\Models\MerchantKey;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rotates a merchant's signing key: the current `active` key moves to
 * `retiring` (still verifiable until `retire_after`, per
 * docs/reference-qr-spec.md "Signing, keys, rotation"), a new key becomes
 * `active`, and `merchants.signing_key_current_kid` points at it. In-flight
 * instruments signed with the outgoing key keep verifying through the
 * overlap window — see QrVerifier, which dual-verifies active + retiring.
 */
class KeyRotator
{
    public function rotate(Merchant $merchant, string $alg, string $secretRef, DateTimeInterface $retireAfter): MerchantKey
    {
        return DB::transaction(function () use ($merchant, $alg, $secretRef, $retireAfter) {
            MerchantKey::where('merchant_id', $merchant->id)
                ->where('state', 'active')
                ->update(['state' => 'retiring', 'retire_after' => $retireAfter]);

            $newKey = MerchantKey::create([
                'merchant_id' => $merchant->id,
                'kid' => (string) Str::uuid(),
                'alg' => $alg,
                'secret_ref' => $secretRef,
                'state' => 'active',
                'activated_at' => now(),
            ]);

            $merchant->update(['signing_key_current_kid' => $newKey->kid]);

            return $newKey;
        });
    }
}
