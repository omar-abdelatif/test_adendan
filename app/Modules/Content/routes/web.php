<?php

/*
 * Content dashboard routes (prefix "admin", web + auth). URLs and names unchanged.
 */

use App\Modules\Content\Http\Controllers\Admin\BoardMembersController;
use App\Modules\Content\Http\Controllers\Admin\CommitteesController;
use App\Modules\Content\Http\Controllers\Admin\NewsController;
use Illuminate\Support\Facades\Route;

Route::prefix('news')->controller(NewsController::class)->group(function () {
    Route::get('all', 'index')->name('news.all');
    Route::post('store_news', 'store')->name('news.store');
    Route::post('update_news', 'update')->name('news.update');
    Route::get('delete_news/{news}', 'destroy')->name('news.destroy');
    Route::get('all_news/all_thumbs/{news}', 'media')->name('show.thumbs');
    Route::get('delete_single_thumbs/{image}', 'destroyImage')->name('thumbs.delete');
    Route::post('store_video/{news}', 'storeVideos')->name('video.store');
    Route::post('update_video', 'updateVideo')->name('video.update');
    Route::get('delete_video/{video}', 'destroyVideo')->name('video.delete');
});

Route::prefix('board')->controller(BoardMembersController::class)->group(function () {
    Route::get('board/all', 'index')->name('board.all');
    Route::post('board/store', 'store')->name('board.store');
    Route::post('board/update', 'update')->name('board.update');
    Route::get('board/delete/{member}', 'destroy')->name('board.delete');
});

Route::prefix('association')->controller(CommitteesController::class)->group(function () {
    Route::get('associations/all', 'index')->name('association.all');
    Route::post('associations/store_association', 'store')->name('association.store');
    Route::post('associations/update_association', 'update')->name('association.update');
    Route::get('associations/delete_association/{committee}', 'destroy')->name('association.delete');
});
