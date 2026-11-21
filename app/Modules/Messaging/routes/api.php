<?php

/*
 * Messaging API routes (prefix "api").
 */

use App\Modules\Messaging\Http\Controllers\Api\MemberMessagingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->controller(MemberMessagingController::class)->group(function () {
    Route::get('sms/sms_status', 'smsStatus');
    Route::get('sms/fees', 'smsFee');
    Route::post('fcm/notify/fcm-token', 'registerDevice');
});
