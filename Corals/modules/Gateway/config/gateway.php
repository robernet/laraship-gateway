<?php
// STUB — module config: void window, fee/commission defaults, contract version.
return [
    // GW-305: default lifetime for issued Issuer API tokens (Sanctum short-TTL requirement).
    'issuer_token_ttl_minutes' => env('GATEWAY_ISSUER_TOKEN_TTL_MINUTES', 1440),

    // GW-401: default lifetime for issued network/POS API tokens.
    'network_token_ttl_minutes' => env('GATEWAY_NETWORK_TOKEN_TTL_MINUTES', 1440),

    // GW-401: how long a /cash/validate reservation holds pos_wallet funds
    // before ReleaseExpiredReservations reclaims it — also the replay-cache
    // TTL fallback when a reference has no expires_at.
    'reservation_ttl_seconds' => env('GATEWAY_RESERVATION_TTL_SECONDS', 300),

    // GW-402: flat v1 pricing (ponytail — one global rate; per-network/
    // per-merchant rate tables are a real future need, not a hypothetical
    // one, but out of scope until a second rate is actually required).
    'commission_bps' => env('GATEWAY_COMMISSION_BPS', 150),
    'fixed_fee_centavos' => env('GATEWAY_FIXED_FEE_CENTAVOS', 50),

    // GW-503: max centavos a network remittance amount may differ from the
    // booked transaction before it opens an amount_mismatch exception.
    'reconciliation_tolerance_centavos' => env('GATEWAY_RECONCILIATION_TOLERANCE_CENTAVOS', 0),

    // GW-505: how long after confirmed_at a transaction stays voidable, and
    // the amount at/above which a void needs a second, distinct approver.
    'void_window_seconds' => env('GATEWAY_VOID_WINDOW_SECONDS', 86400),
    'void_dual_control_threshold_centavos' => env('GATEWAY_VOID_DUAL_CONTROL_THRESHOLD_CENTAVOS', 500000),

    // GW-504: admin resource config consumed by Corals\Foundation's
    // ModelHelpersTrait/ModelActionsTrait (edit-URL generation, action
    // buttons) — mirrors Corals/core/Activity/config/activity.php "models".
    'models' => [
        'reconciliation_exception' => [
            'resource_url' => 'reconciliation-exceptions',
            'actions' => [
                'edit' => [],
            ],
        ],
    ],
];
