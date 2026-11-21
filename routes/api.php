<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NewsCommentsController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\OcrController;
use App\Http\Controllers\Api\SMSController;
use App\Http\Controllers\Api\TombController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WeddingsController;
use App\Http\Controllers\Api\WorkersController;
use App\Http\Controllers\NotificationsController;
use App\Http\Middleware\VerifyOcrApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::post('/ocr-data', [OcrController::class, 'store'])->middleware([VerifyOcrApiKey::class, 'throttle:public-lookup']);
    Route::post('/member/login', [AuthController::class, 'login'])->middleware('throttle:member-login');
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
    Route::prefix('user')->group(function () {
        Route::controller(UserController::class)->group(function () {
            Route::put('/update', 'updateUser');
            Route::get('update_is_pending/{member_id}', 'updateIsPending');
        });
        Route::controller(AuthController::class)->group(function () {
            Route::post('logout', 'logout');
            Route::get('me', 'user');
        });
    });
    // Payments, dues and SMS checkout live in app/Modules/Finance/routes/api.php.
    Route::prefix('sms')->controller(SMSController::class)->group(function () {
        Route::get('sms_status', 'getSmsStatus');
        Route::get('fees', 'getSmsFees');
    });
    Route::prefix('fcm/notify')->controller(NotificationsController::class)->group(function () {
        Route::post('fcm-token', 'updateFcmToken');
    });
});
