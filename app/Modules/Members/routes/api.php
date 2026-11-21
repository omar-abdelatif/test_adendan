<?php

/*
 * Members API routes (prefix "api", api middleware).
 */

use App\Http\Middleware\VerifyOcrApiKey;
use App\Modules\Members\Http\Controllers\Api\AuthController;
use App\Modules\Members\Http\Controllers\Api\OcrController;
use App\Modules\Members\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::post('ocr-data', [OcrController::class, 'store'])->middleware([VerifyOcrApiKey::class, 'throttle:public-lookup']);
Route::post('member/login', [AuthController::class, 'login'])->middleware('throttle:member-login');

Route::middleware('auth:sanctum')->prefix('user')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::put('update', [ProfileController::class, 'requestUpdate']);
    Route::get('update_is_pending/{member_id}', [ProfileController::class, 'hasPendingUpdate']);
});
