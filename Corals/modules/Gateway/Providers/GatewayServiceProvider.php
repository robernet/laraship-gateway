<?php

namespace Corals\Modules\Gateway\Providers;

use Corals\Foundation\Facades\Actions;
use Corals\Foundation\Providers\BasePackageServiceProvider;
use Corals\Modules\Gateway\Commands\DailyCloseIntegrityCheck;
use Corals\Modules\Gateway\Commands\IssueIssuerToken;
use Corals\Modules\Gateway\Commands\IssueNetworkToken;
use Corals\Modules\Gateway\Commands\IssueTerminalToken;
use Corals\Modules\Gateway\Commands\RedeliverWebhooks;
use Corals\Modules\Gateway\Commands\ReleaseExpiredReservations;
use Corals\Modules\Gateway\Commands\RevokeTerminalToken;
use Corals\Modules\Gateway\Commands\SetIssuerPassword;
use Corals\Modules\Gateway\Core\Webhooks\WebhookDispatcher;
use Corals\Settings\Facades\Modules;
use Illuminate\Auth\Middleware\Authenticate;
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

        Route::prefix('api/'.config('corals.api_version'))
            ->middleware('api')
            ->group(__DIR__.'/../routes/pos.php');

        Route::prefix('portal')
            ->middleware('web')
            ->group(__DIR__.'/../routes/portal.php');

        // The app registers no global Authenticate::redirectUsing() callback,
        // so an unauthenticated non-JSON request gets a bare 401 everywhere
        // (see Illuminate\Foundation\Exceptions\Handler::unauthenticated).
        // Scoped to portal/* only, so every other guard's behavior is
        // unchanged — a guest hitting the portal gets sent to its login page.
        Authenticate::redirectUsing(function ($request) {
            if ($request->is('portal*')) {
                return route('gateway.portal.login');
            }
        });

        $this->commands([
            DailyCloseIntegrityCheck::class,
            RedeliverWebhooks::class,
            IssueIssuerToken::class,
            SetIssuerPassword::class,
            IssueNetworkToken::class,
            IssueTerminalToken::class,
            RevokeTerminalToken::class,
            ReleaseExpiredReservations::class,
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

            $this->app->make(Schedule::class)
                ->command(ReleaseExpiredReservations::class)
                ->everyMinute();
        });
    }

    public function registerModulesPackages()
    {
        Modules::addModulesPackages('corals/gateway');
    }
}
