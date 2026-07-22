<?php

namespace Corals\Modules\Gateway\Core\References;

/**
 * Stands in for the real KMS/HSM lookup described in
 * docs/reference-qr-spec.md ("Keys are never stored raw; only KMS/HSM
 * handles"). v1 is clean-room + mock networks (module CLAUDE.md); this
 * deterministically derives signing bytes from the stored handle so
 * sign/verify round-trip without ever persisting a raw secret.
 *
 * ponytail: mock only, ceiling is "no real key custody". Swap for an actual
 * KMS/HSM client when the module signs against production merchant keys.
 */
class MockKeyVault
{
    public static function resolve(string $secretRef): string
    {
        return hash_hmac('sha256', $secretRef, config('app.key'));
    }
}
