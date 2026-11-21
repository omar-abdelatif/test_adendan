<?php

/*
 * Public website routes for content (web middleware, names prefixed "site.").
 */

use App\Modules\Content\Http\Controllers\Site\SiteController;
use Illuminate\Support\Facades\Route;

Route::name('site.')->controller(SiteController::class)->group(function () {
    Route::get('/', 'home')->name('index');
    Route::get('all_news', 'news')->name('news');
    Route::get('all_news/single_news/{slug}', 'newsDetails')->name('single_news');
    Route::get('board_members', 'board')->name('borders');
    Route::get('assossiation_committees', 'committees')->name('assossiation');
    Route::get('assossiation_committees/assossiation_details/{committee}', 'committee')->name('assossiation_details');
});
