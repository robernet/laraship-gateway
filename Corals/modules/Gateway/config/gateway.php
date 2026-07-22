<?php
// STUB — module config: void window, fee/commission defaults, replay TTL, contract version.
return [
    // GW-305: default lifetime for issued Issuer API tokens (Sanctum short-TTL requirement).
    'issuer_token_ttl_minutes' => env('GATEWAY_ISSUER_TOKEN_TTL_MINUTES', 1440),
];
