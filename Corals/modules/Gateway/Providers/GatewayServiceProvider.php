<?php

namespace Corals\Modules\Gateway\Providers;

use Corals\Foundation\Providers\BasePackageServiceProvider;
use Corals\Modules\Gateway\Commands\DailyCloseIntegrityCheck;
use Corals\Settings\Facades\Modules;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Minimal registration only (config/views/lang). Routes, policies, and the AdapterGateway
 * binding are wired in later tickets — see Corals/modules/Gateway/CLAUDE.md for invariants.
 */
class GatewayServiceProvider extends BasePackageServiceProvider
{
    protected $packageCode = 'corals-gateway';

    public function registerPackage()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/gateway.php', 'gateway');
    }

    public function bootPackage()
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'Gateway');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'Gateway');

        $this->commands([
            DailyCloseIntegrityCheck::class,
        ]);

        $this->app->booted(function () {
            $this->app->make(Schedule::class)
                ->command(DailyCloseIntegrityCheck::class)
                ->dailyAt('00:15');
        });
    }

    public function registerModulesPackages()
    {
        Modules::addModulesPackages('corals/gateway');
    }
}
