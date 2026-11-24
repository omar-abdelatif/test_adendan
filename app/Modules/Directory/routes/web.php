<?php

/*
 * Directory dashboard routes (prefix "admin", web + auth). URLs and names unchanged.
 */

use App\Modules\Directory\Http\Controllers\Admin\WeddingsController;
use App\Modules\Directory\Http\Controllers\Admin\WorkersController;
use Illuminate\Support\Facades\Route;

// Tombs moved to the Cemeteries module; the old address still lands on its overview.
Route::redirect('tomb/tombs/all', '/admin/cemeteries')->name('tomb.all');

Route::prefix('weddings')->controller(WeddingsController::class)->group(function () {
    Route::get('weddings/all', 'index')->name('weddings.all');
    Route::post('wedding/store', 'store')->name('weddings.store');
    Route::post('wedding/update', 'update')->name('weddings.update');
    Route::delete('wedding/delete/{wedding}', 'destroy')->name('wedding.delete');
    Route::post('wedding/bulk/upload', 'import')->name('weddings.bulk');
});

Route::prefix('workers')->controller(WorkersController::class)->group(function () {
    Route::get('workers/all', 'index')->name('workers.all');
    Route::post('workers/store', 'store')->name('worker.store');
    Route::post('worker/update', 'update')->name('worker.update');
    Route::delete('worker/delete/{worker}', 'destroy')->name('worker.delete');
});
