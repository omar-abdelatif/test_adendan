<?php

/*
 * API routes not yet moved into a module. Module routes:
 * - app/Modules/Finance/routes/api.php (payments, dues, checkout)
 * - app/Modules/Members/routes/api.php (login, profile, OCR)
 */

use App\Http\Controllers\Api\NewsCommentsController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\SMSController;
use App\Http\Controllers\Api\TombController;
use App\Http\Controllers\Api\WeddingsController;
use App\Http\Controllers\Api\WorkersController;
use App\Http\Controllers\NotificationsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::prefix('/news')->controller(NewsController::class)->group(function () {
        Route::get('/all', 'allNews');
        Route::get('/limited_news', 'latestNews');
        Route::get('/{id}', 'show');
        Route::controller(NewsCommentsController::class)->group(function () {
            Route::get('/{newsId}/comments', 'getComments');
            Route::post('/{newsId}/comments', 'store')->middleware('throttle:public-lookup');
        });
    });
    Route::controller(TombController::class)->group(function () {
        Route::get('/all_tombs', 'index');
    });
    Route::controller(WorkersController::class)->group(function () {
        Route::get('/all_workers', 'index');
    });
    Route::controller(WeddingsController::class)->group(function () {
        Route::get('/all_weddings', 'index');
    });
});
Route::middleware('auth:sanctum')->group(function () {
    // Editing or deleting a comment needs the member who wrote it.
    Route::prefix('/news')->controller(NewsCommentsController::class)->group(function () {
        Route::put('/{newsId}/comments/{commentId}', 'update');
        Route::delete('/{newsId}/comments/{commentId}', 'destroy');
    });
    Route::prefix('sms')->controller(SMSController::class)->group(function () {
        Route::get('sms_status', 'getSmsStatus');
        Route::get('fees', 'getSmsFees');
    });
    Route::prefix('fcm/notify')->controller(NotificationsController::class)->group(function () {
        Route::post('fcm-token', 'updateFcmToken');
    });
});
