<?php

use Corals\Modules\Gateway\Http\Controllers\Portal\ApiKeyController;
use Corals\Modules\Gateway\Http\Controllers\Portal\AuthController;
use Corals\Modules\Gateway\Http\Controllers\Portal\DocsController;
use Corals\Modules\Gateway\Http\Controllers\Portal\PaymentIntentController;
use Corals\Modules\Gateway\Http\Controllers\Portal\SimulatorController;
use Corals\Modules\Gateway\Http\Controllers\Portal\WebhookSettingsController;
use Illuminate\Support\Facades\Route;

// Issuer portal (GW-306) — session-authenticated via the `issuer` guard,
// separate from the Sanctum-bearer-token Issuer API in routes/issuer.php.
// Nested under auth/ rather than a bare "login" segment: Corals\User
// registers a `{role_name?}/login` wildcard (Corals/core/User/routes/web.php)
// that would otherwise match "portal/login" first and shadow this route.
Route::get('auth/login', [AuthController::class, 'showLogin'])->name('gateway.portal.login');
Route::post('auth/login', [AuthController::class, 'login'])->name('gateway.portal.login.attempt');
Route::post('logout', [AuthController::class, 'logout'])->name('gateway.portal.logout');

Route::get('/', [PaymentIntentController::class, 'index'])->name('gateway.portal.dashboard');
Route::get('payment-intents', [PaymentIntentController::class, 'list'])->name('gateway.portal.payment-intents.list');
Route::post('payment-intents', [PaymentIntentController::class, 'store'])->name('gateway.portal.payment-intents.store');
Route::get('invoices/{invoice_id}/status', [PaymentIntentController::class, 'invoiceStatus'])->name('gateway.portal.invoices.status');

Route::get('api-keys', [ApiKeyController::class, 'index'])->name('gateway.portal.api-keys.index');
Route::post('api-keys', [ApiKeyController::class, 'store'])->name('gateway.portal.api-keys.store');
Route::delete('api-keys/{token}', [ApiKeyController::class, 'destroy'])->name('gateway.portal.api-keys.destroy');

Route::get('webhook', [WebhookSettingsController::class, 'edit'])->name('gateway.portal.webhook.edit');
Route::put('webhook', [WebhookSettingsController::class, 'update'])->name('gateway.portal.webhook.update');
Route::post('webhook/test', [WebhookSettingsController::class, 'test'])->name('gateway.portal.webhook.test');

Route::get('docs', [DocsController::class, 'index'])->name('gateway.portal.docs');
Route::get('docs/openapi.yaml', [DocsController::class, 'spec'])->name('gateway.portal.docs.spec');

Route::get('simulator', [SimulatorController::class, 'index'])->name('gateway.portal.simulator.index');
Route::post('simulator/intents', [SimulatorController::class, 'createIntent'])->name('gateway.portal.simulator.intents.store');
Route::post('simulator/validate', [SimulatorController::class, 'validateCollection'])->name('gateway.portal.simulator.validate');
Route::post('simulator/confirm', [SimulatorController::class, 'confirmCollection'])->name('gateway.portal.simulator.confirm');
