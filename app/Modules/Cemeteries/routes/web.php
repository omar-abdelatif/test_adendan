<?php

/*
 * Cemeteries dashboard routes (prefix "admin", web + auth middleware).
 * Every controller here requires the "المقابر" permission.
 */

use App\Modules\Cemeteries\Http\Controllers\Admin\BurialsController;
use App\Modules\Cemeteries\Http\Controllers\Admin\CemeteriesController;
use App\Modules\Cemeteries\Http\Controllers\Admin\TombsController;
use Illuminate\Support\Facades\Route;

Route::prefix('cemeteries')->name('cemeteries.')->group(function () {
    Route::get('/', [CemeteriesController::class, 'index'])->name('index');
    Route::post('regions', [CemeteriesController::class, 'storeRegion'])->name('regions.store');

    Route::controller(TombsController::class)->group(function () {
        Route::post('tombs', 'store')->name('tombs.store');
        Route::get('tombs/{tomb}', 'show')->name('tombs.show');
        Route::put('tombs/{tomb}', 'update')->name('tombs.update');
        Route::delete('tombs/{tomb}', 'destroy')->name('tombs.destroy');
        Route::post('chambers/{chamber}/clear', 'clearChamber')->name('chambers.clear');
    });

    Route::controller(BurialsController::class)->group(function () {
        Route::get('burials', 'index')->name('burials.index');
        Route::post('burials', 'store')->name('burials.store');
        Route::post('burials/import', 'import')->name('burials.import');
        Route::put('burials/{burial}', 'update')->name('burials.update');
        Route::delete('burials/{burial}', 'destroy')->name('burials.destroy');
        Route::get('files/{file}', 'file')->name('files.show');
    });
});
