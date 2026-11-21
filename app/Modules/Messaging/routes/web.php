<?php

/*
 * SMS dashboard routes (prefix "admin", web + auth). URLs and names unchanged.
 */

use App\Modules\Messaging\Http\Controllers\Admin\SmsController;
use Illuminate\Support\Facades\Route;

Route::controller(SmsController::class)->group(function () {
    Route::prefix('sms')->name('sms.')->group(function () {
        Route::get('sms', 'index')->name('index');
        Route::post('store_sms_fees', 'updateFee')->name('updateFees');
        Route::get('create_new', 'create')->name('createNew');
        Route::post('send_msg', 'send')->name('storeMsg');
        Route::put('renew_sub/{id}', 'renew')->name('renew');
        Route::post('bulk_store', 'import')->name('bulkstore');
        Route::post('store_subscriber', 'store')->name('storeSubscriber');
        Route::get('balance', 'balance')->name('balance');
        Route::delete('delete_subscriber/{id}', 'destroy')->name('deleteSubscriber');
    });

    Route::post('subscription/subscriptions/notify-expiry', 'sendReminders')->name('subscription.notify-expiry');
});
