<?php
// GW-504/GW-506: admin routes — reconciliation exceptions queue + ops console, via BaseController + DataTables.

use Corals\Modules\Gateway\Http\Controllers\OpsConsoleController;
use Corals\Modules\Gateway\Http\Controllers\ReconciliationExceptionsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'reconciliation-exceptions', 'as' => 'gateway.reconciliation-exceptions.'], function () {
    Route::get('/', [ReconciliationExceptionsController::class, 'index'])->name('index');
    Route::get('{reconciliation_exception}/edit', [ReconciliationExceptionsController::class, 'edit'])->name('edit');
    Route::put('{reconciliation_exception}', [ReconciliationExceptionsController::class, 'update'])->name('update');
});

Route::group(['prefix' => 'ops', 'as' => 'gateway.ops.'], function () {
    Route::get('wallets', [OpsConsoleController::class, 'wallets'])->name('wallets');
    Route::get('top-ups', [OpsConsoleController::class, 'topUps'])->name('top-ups');
    Route::get('transactions', [OpsConsoleController::class, 'transactions'])->name('transactions');
    Route::get('audit-log', [OpsConsoleController::class, 'auditLog'])->name('audit-log');
});
