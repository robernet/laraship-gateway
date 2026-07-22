<?php

namespace Corals\Modules\Gateway\Providers;

use Corals\Foundation\Providers\BasePackageServiceProvider;
use Corals\Settings\Facades\Modules;

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
    }

    public function registerModulesPackages()
    {
        Modules::addModulesPackages('corals/gateway');
    }
}
