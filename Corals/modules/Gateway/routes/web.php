<?php
// GW-504: admin routes — reconciliation exceptions queue via BaseController + DataTables.

use Corals\Modules\Gateway\Http\Controllers\ReconciliationExceptionsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'reconciliation-exceptions', 'as' => 'gateway.reconciliation-exceptions.'], function () {
    Route::get('/', [ReconciliationExceptionsController::class, 'index'])->name('index');
    Route::get('{reconciliation_exception}/edit', [ReconciliationExceptionsController::class, 'edit'])->name('edit');
    Route::put('{reconciliation_exception}', [ReconciliationExceptionsController::class, 'update'])->name('update');
});
