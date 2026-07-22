<?php

namespace Tests\Unit;

use Corals\Modules\Gateway\Core\References\Mod97Check;
use PHPUnit\Framework\TestCase;

class Mod97CheckTest extends TestCase
{
    public function test_compute_returns_two_digits_that_verify(): void
    {
        $check = Mod97Check::compute('0123456789');

        $this->assertSame(2, strlen($check));
        $this->assertTrue(Mod97Check::verify('0123456789', $check));
    }

    public function test_single_digit_error_is_caught(): void
    {
        $token = '4837291056';
        $check = Mod97Check::compute($token);

        foreach (str_split($token) as $position => $digit) {
            $mutated = $token;
            $mutated[$position] = (string) (((int) $digit + 1) % 10);

            $this->assertFalse(
                Mod97Check::verify($mutated, $check),
                "Single-digit error at position {$position} was not caught."
            );
        }
    }

    public function test_adjacent_transposition_error_is_caught(): void
    {
        $token = '4837291056';
        $check = Mod97Check::compute($token);

        for ($position = 0; $position < strlen($token) - 1; $position++) {
            if ($token[$position] === $token[$position + 1]) {
                continue;
            }

            $mutated = $token;
            [$mutated[$position], $mutated[$position + 1]] = [$mutated[$position + 1], $mutated[$position]];

            $this->assertFalse(
                Mod97Check::verify($mutated, $check),
                "Transposition error at position {$position} was not caught."
            );
        }
    }
}
