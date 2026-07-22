<?php

namespace Corals\Modules\Gateway\Core\References;

/**
 * ISO 7064 MOD 97-10 check digits over a numeric string. Two digits, always
 * in range 02-98, so a two-zero pair can never be a valid check (cheap
 * "did I even compute this" guard). Catches single-digit errors and adjacent
 * transpositions, per docs/reference-qr-spec.md.
 */
class Mod97Check
{
    public static function compute(string $digits): string
    {
        return str_pad((string) (98 - self::mod97($digits.'00')), 2, '0', STR_PAD_LEFT);
    }

    public static function verify(string $digits, string $checkDigits): bool
    {
        return self::compute($digits) === $checkDigits;
    }

    /**
     * Digit-by-digit modulus (Horner's method) so arbitrarily long numeric
     * strings (e.g. GW-203's 19-digit MID+token) never overflow PHP's
     * 64-bit int via a naive (int) cast.
     */
    private static function mod97(string $digits): int
    {
        $remainder = 0;

        foreach (str_split($digits) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder;
    }
}
