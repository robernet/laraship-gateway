<?php
// POS API routes: /v1/cash/{validate,confirm,batch-confirm}. Sanctum + idempotency.
// confirm/batch-confirm land in GW-402/GW-403.

use Corals\Modules\Gateway\Core\Networks\NetworkAbility;
use Corals\Modules\Gateway\Http\Controllers\Pos\CashController;
use Illuminate\Support\Facades\Route;

Route::post('cash/validate', [CashController::class, 'validateCollection'])
    ->middleware(['auth:sanctum', 'abilities:'.NetworkAbility::ValidateCollection->value]);
