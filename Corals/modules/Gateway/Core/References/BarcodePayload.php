<?php

namespace Corals\Modules\Gateway\Core\References;

use InvalidArgumentException;

/**
 * Code128-C barcode payload: MID(9) + token(10) + checksum(2), 21 numeric
 * digits. See docs/reference-qr-spec.md ("Barcode"). Checksum is MOD 97-10
 * over MID+token, distinct from the human_reference's own check digits.
 */
class BarcodePayload
{
    public static function encode(string $mid, string $token): string
    {
        if (strlen($mid) !== 9 || ! ctype_digit($mid)) {
            throw new InvalidArgumentException('mid must be exactly 9 digits.');
        }

        if (strlen($token) !== 10 || ! ctype_digit($token)) {
            throw new InvalidArgumentException('token must be exactly 10 digits.');
        }

        return $mid.$token.Mod97Check::compute($mid.$token);
    }

    /**
     * @return array{mid: string, token: string}
     */
    public static function decode(string $payload): array
    {
        if (strlen($payload) !== 21 || ! ctype_digit($payload)) {
            throw new InvalidArgumentException('payload must be exactly 21 digits.');
        }

        $mid = substr($payload, 0, 9);
        $token = substr($payload, 9, 10);
        $checksum = substr($payload, 19, 2);

        if (! Mod97Check::verify($mid.$token, $checksum)) {
            throw new InvalidArgumentException('payload checksum is invalid.');
        }

        return ['mid' => $mid, 'token' => $token];
    }
}
