<?php

/*
 * Website pages (web middleware) and mobile API lists (api middleware) for
 * the directory; registered by DirectoryServiceProvider.
 */

use App\Modules\Directory\Http\Controllers\PublicDirectoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->name('site.')->controller(PublicDirectoryController::class)->group(function () {
    Route::get('search', 'searchPage')->name('search');
    Route::get('workers/all', 'workersPage')->name('workers');
});

Route::middleware('api')->prefix('api')->controller(PublicDirectoryController::class)->group(function () {
    Route::get('all_tombs', 'tombs');
    Route::get('all_weddings', 'weddings');
    Route::get('all_workers', 'workers');
});
