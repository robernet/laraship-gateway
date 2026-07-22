<?php

namespace Corals\Modules\Gateway\Providers;

use Corals\Foundation\Facades\Actions;
use Corals\Foundation\Providers\BasePackageServiceProvider;
use Corals\Modules\Gateway\Commands\DailyCloseIntegrityCheck;
use Corals\Modules\Gateway\Commands\IssueIssuerToken;
use Corals\Modules\Gateway\Commands\RedeliverWebhooks;
use Corals\Modules\Gateway\Core\Webhooks\WebhookDispatcher;
use Corals\Settings\Facades\Modules;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;

/**
 * Minimal registration only (config/views/lang). Policies and the AdapterGateway
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

        Route::prefix('api/'.config('corals.api_version'))
            ->middleware('api')
            ->group(__DIR__.'/../routes/issuer.php');

        $this->commands([
            DailyCloseIntegrityCheck::class,
            RedeliverWebhooks::class,
            IssueIssuerToken::class,
        ]);

        foreach (['payment.confirmed', 'payment.credited', 'payment.expired', 'payment.voided', 'settlement.completed'] as $event) {
            Actions::add_action($event, fn (array $payload) => (new WebhookDispatcher())->notify($event, $payload));
        }

        $this->app->booted(function () {
            $this->app->make(Schedule::class)
                ->command(DailyCloseIntegrityCheck::class)
                ->dailyAt('00:15');

            $this->app->make(Schedule::class)
                ->command(RedeliverWebhooks::class)
                ->everyFiveMinutes();
        });
    }

    public function registerModulesPackages()
    {
        Modules::addModulesPackages('corals/gateway');
    }
}
