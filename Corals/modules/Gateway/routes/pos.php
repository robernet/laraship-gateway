<?php
// POS API routes: /v1/cash/{validate,confirm,batch-confirm}. Sanctum + idempotency.
// batch-confirm lands in GW-403.

use Corals\Modules\Gateway\Core\Networks\NetworkAbility;
use Corals\Modules\Gateway\Http\Controllers\Pos\CashController;
use Corals\Modules\Gateway\Http\Middleware\EnsureTerminalCredentialActive;
use Illuminate\Support\Facades\Route;

Route::post('cash/validate', [CashController::class, 'validateCollection'])
    ->middleware(['auth:sanctum', 'abilities:'.NetworkAbility::ValidateCollection->value, EnsureTerminalCredentialActive::class]);

Route::post('cash/confirm', [CashController::class, 'confirmCollection'])
    ->middleware(['auth:sanctum', 'abilities:'.NetworkAbility::ConfirmCollection->value, EnsureTerminalCredentialActive::class]);

Route::post('cash/batch-confirm', [CashController::class, 'batchConfirm'])
    ->middleware(['auth:sanctum', 'abilities:'.NetworkAbility::BatchConfirm->value]);
