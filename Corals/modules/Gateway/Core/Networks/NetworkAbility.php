<?php

namespace Corals\Modules\Gateway\Core\Networks;

/**
 * Sanctum ability strings for network/POS API tokens (GW-401). Enforced via
 * the `abilities` middleware on routes/pos.php.
 */
enum NetworkAbility: string
{
    case ValidateCollection = 'cash:validate';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
