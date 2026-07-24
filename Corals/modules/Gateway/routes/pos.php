<?php
// POS API routes: /v1/cash/{validate,confirm,batch-confirm}. Sanctum + idempotency.
// batch-confirm lands in GW-403.

use Corals\Modules\Gateway\Core\Networks\NetworkAbility;
use Corals\Modules\Gateway\Http\Controllers\Pos\CashController;
use Illuminate\Support\Facades\Route;

Route::post('cash/validate', [CashController::class, 'validateCollection'])
    ->middleware(['auth:sanctum', 'abilities:'.NetworkAbility::ValidateCollection->value]);

Route::post('cash/confirm', [CashController::class, 'confirmCollection'])
    ->middleware(['auth:sanctum', 'abilities:'.NetworkAbility::ConfirmCollection->value]);
