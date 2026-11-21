<?php

/*
 * Finance API routes, loaded under prefix "api" with the api middleware.
 */

use App\Modules\Finance\Http\Controllers\Api\CheckoutController;
use App\Modules\Finance\Http\Controllers\Api\MemberFinanceController;
use App\Modules\Finance\Http\Controllers\Api\PaymobCallbackController;
use Illuminate\Support\Facades\Route;

// Paymob server-to-server callback (both URLs are configured in Paymob dashboards).
Route::match(['get', 'post'], 'paymob/callback', PaymobCallbackController::class);
Route::match(['get', 'post'], 'pay/callback', PaymobCallbackController::class);

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('payments/checkout', [CheckoutController::class, 'due'])->name('api.payments.checkout');
    Route::post('sms/pay_subscription', [CheckoutController::class, 'sms'])->name('api.sms.checkout');
    Route::post('sms/renew_subscription', [CheckoutController::class, 'sms'])->name('api.sms.renew');

    Route::controller(MemberFinanceController::class)->group(function () {
        Route::get('subscriber/subscription-payments-history', 'subscriptionPayments');
        Route::get('subscriber/donations-payments-history', 'donationPayments');
        Route::get('subscriber/subscription-dues', 'subscriptionDues');
        Route::get('subscriber/donation-dues', 'donationDues');
        Route::get('sms/payment_history', 'smsPayments');
    });
});
