<?php

namespace Corals\Modules\Gateway\Core\References;

use Illuminate\Support\Facades\Redis;

/**
 * QR/API nonce replay cache (docs/reference-qr-spec.md "Anti-replay"):
 * "Second sight of the same non within TTL -> reject." Atomic SET NX EX so
 * two concurrent sightings of the same nonce can't both win the race.
 */
class ReplayCache
{
    private const PREFIX = 'gateway:qr:nonce:';

    /**
     * Records the nonce if it hasn't been seen before. Returns true on a
     * first sighting (now recorded), false if it's a replay.
     */
    public function markIfNew(string $nonce, int $ttlSeconds): bool
    {
        $result = Redis::set(self::PREFIX.$nonce, 1, 'EX', max($ttlSeconds, 1), 'NX');

        return $result !== null;
    }
}
