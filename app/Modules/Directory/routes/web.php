<?php

/*
 * Directory dashboard routes (prefix "admin", web + auth). URLs and names unchanged.
 */

use App\Modules\Directory\Http\Controllers\Admin\TombsController;
use App\Modules\Directory\Http\Controllers\Admin\WeddingsController;
use App\Modules\Directory\Http\Controllers\Admin\WorkersController;
use Illuminate\Support\Facades\Route;

Route::prefix('tomb')->controller(TombsController::class)->group(function () {
    Route::get('tombs/all', 'index')->name('tomb.all');
    Route::post('tombs/store', 'store')->name('tomb.store');
    Route::post('tombs/update', 'update')->name('tomb.update');
    Route::get('tombs/delete/{tomb}', 'destroy')->name('tomb.delete');
});

Route::prefix('weddings')->controller(WeddingsController::class)->group(function () {
    Route::get('weddings/all', 'index')->name('weddings.all');
    Route::post('wedding/store', 'store')->name('weddings.store');
    Route::post('wedding/update', 'update')->name('weddings.update');
    Route::get('wedding/delete/{wedding}', 'destroy')->name('wedding.delete');
    Route::post('wedding/bulk/upload', 'import')->name('weddings.bulk');
});

Route::prefix('workers')->controller(WorkersController::class)->group(function () {
    Route::get('workers/all', 'index')->name('workers.all');
    Route::post('workers/store', 'store')->name('worker.store');
    Route::post('worker/update', 'update')->name('worker.update');
    Route::get('worker/delete/{worker}', 'destroy')->name('worker.delete');
});
