<?php

/*
 * Public website routes for members (web middleware, names prefixed "site.").
 */

use App\Modules\Members\Http\Controllers\Site\MemberLookupController;
use Illuminate\Support\Facades\Route;

Route::name('site.')->controller(MemberLookupController::class)->group(function () {
    Route::post('result', 'result')->middleware('throttle:public-lookup')->name('result');
    Route::get('search-details/{slug}', 'details')->name('searchDetails');
    Route::post('store-member-main-data', 'storeRequest')->middleware('throttle:public-lookup')->name('storeMemberMainData');
});
