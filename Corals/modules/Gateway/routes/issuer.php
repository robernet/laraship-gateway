<?php

use Corals\Modules\Gateway\Core\Issuers\IssuerAbility;
use Corals\Modules\Gateway\Http\Controllers\Issuer\PaymentIntentController;
use Illuminate\Support\Facades\Route;

Route::post('payment-intents', [PaymentIntentController::class, 'store'])
    ->middleware('abilities:'.IssuerAbility::CreatePaymentIntents->value);
Route::get('payment-intents/{id}', [PaymentIntentController::class, 'show'])
    ->middleware('abilities:'.IssuerAbility::ReadPaymentIntents->value);
Route::get('invoices/{invoice_id}/status', [PaymentIntentController::class, 'statusByInvoice'])
    ->middleware('abilities:'.IssuerAbility::ReadPaymentIntents->value);
