<?php

/*
 * Finance API routes, loaded under prefix "api" with the api middleware.
 */

use App\Modules\Finance\Http\Controllers\Api\LegacySmsPaymentController;
use App\Modules\Finance\Http\Controllers\Api\MemberFinanceController;
use App\Modules\Finance\Http\Controllers\Api\PaymentReturnController;
use App\Modules\Finance\Http\Controllers\Api\PaymentsController;
use App\Modules\Finance\Http\Controllers\Api\PaymobWebhookController;
use Illuminate\Support\Facades\Route;

// Paymob "transaction processed" callback: the only path that marks a payment paid.
// paymob/callback is the older URL, kept so a dashboard still pointing there keeps working.
Route::post('payments/paymob/webhook', PaymobWebhookController::class)->name('api.payments.webhook');
Route::post('paymob/callback', PaymobWebhookController::class);

// Where the member's browser lands after paying. Records nothing.
Route::match(['get', 'post'], 'pay/callback', PaymentReturnController::class)->name('api.payments.return');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('payments/checkout', [PaymentsController::class, 'checkout'])->name('api.payments.checkout');
    Route::get('payments/{payment_id}', [PaymentsController::class, 'show'])->name('api.payments.show');

    // Older app versions: answer with the SMS status, activate nothing.
    Route::post('sms/pay_subscription', LegacySmsPaymentController::class)->name('api.sms.checkout');
    Route::post('sms/renew_subscription', LegacySmsPaymentController::class)->name('api.sms.renew');

    Route::controller(MemberFinanceController::class)->group(function () {
        Route::get('subscriber/subscription-payments-history', 'subscriptionPayments');
        Route::get('subscriber/donations-payments-history', 'donationPayments');
        Route::get('subscriber/subscription-dues', 'subscriptionDues');
        Route::get('subscriber/donation-dues', 'donationDues');
        Route::get('sms/payment_history', 'smsPayments');
    });
});
