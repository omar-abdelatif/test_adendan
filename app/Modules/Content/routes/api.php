<?php

/*
 * Content API routes (prefix "api").
 */

use App\Modules\Content\Http\Controllers\Api\NewsCommentsController;
use App\Modules\Content\Http\Controllers\Api\NewsController;
use Illuminate\Support\Facades\Route;

Route::prefix('news')->group(function () {
    Route::get('all', [NewsController::class, 'index']);
    Route::get('limited_news', [NewsController::class, 'latest']);
    Route::get('{id}', [NewsController::class, 'show'])->whereNumber('id');

    Route::controller(NewsCommentsController::class)->group(function () {
        Route::get('{newsId}/comments', 'getComments');
        Route::post('{newsId}/comments', 'store')->middleware('throttle:public-lookup');

        // Editing or deleting a comment needs the member who wrote it.
        Route::middleware('auth:sanctum')->group(function () {
            Route::put('{newsId}/comments/{commentId}', 'update');
            Route::delete('{newsId}/comments/{commentId}', 'destroy');
        });
    });
});
