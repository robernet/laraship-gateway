<?php

namespace Corals\Modules\Gateway\Core\Issuers;

/**
 * Sanctum ability strings for issuer API tokens (GW-305). Enforced via the
 * `abilities` middleware on routes/issuer.php.
 */
enum IssuerAbility: string
{
    case CreatePaymentIntents = 'payment-intents:write';
    case ReadPaymentIntents = 'payment-intents:read';

    /**
     * Marker ability (GW-307): tags payment intents created with this token
     * as sandbox/test data. Deliberately excluded from the portal's
     * "select all by default" token-creation form — see
     * Portal\ApiKeyController.
     */
    case Sandbox = 'sandbox';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
