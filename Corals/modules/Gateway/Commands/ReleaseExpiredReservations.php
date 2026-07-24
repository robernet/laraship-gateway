<?php

namespace Corals\Modules\Gateway\Commands;

use Corals\Modules\Gateway\Core\Collections\ReleaseExpiredReservations as ReleaseExpiredReservationsCore;
use Illuminate\Console\Command;

/**
 * Scheduled sweep for GW-401: releases pos_wallet reservations held by
 * AUTHORIZED transactions that never reached confirm within the
 * reservation window.
 */
class ReleaseExpiredReservations extends Command
{
    protected $signature = 'gateway:release-expired-reservations';

    protected $description = 'Releases pos_wallet reservations for transactions that timed out before confirm.';

    public function handle(ReleaseExpiredReservationsCore $releaser): int
    {
        $count = $releaser->handle();

        $this->info("Released {$count} expired reservations.");

        return self::SUCCESS;
    }
}
