<?php

/*
 * Sponsorship dashboard routes (prefix "admin", web + auth middleware).
 * Every controller here requires the "الكفالة" permission. The sponsorship
 * fund's donors, donations, safe and bank are fund pages in the Finance module
 * (admin/funds/sponsorship/...).
 */

use App\Modules\Sponsorship\Http\Controllers\Admin\CasesController;
use App\Modules\Sponsorship\Http\Controllers\Admin\SponsorshipReportsController;
use Illuminate\Support\Facades\Route;

Route::prefix('sponsorship')->name('sponsorship.')->group(function () {
    Route::controller(CasesController::class)->group(function () {
        Route::get('cases', 'index')->name('cases.index');
        Route::post('cases', 'store')->name('cases.store');
        Route::post('cases/import', 'import')->name('cases.import');
        Route::get('cases/{case}', 'show')->name('cases.show');
        Route::put('cases/{case}', 'update')->name('cases.update');
        Route::delete('cases/{case}', 'destroy')->name('cases.destroy');
        Route::post('cases/{case}/files', 'addFiles')->name('cases.files.store');
        Route::get('files/{file}', 'file')->name('files.show');
        Route::delete('files/{file}', 'deleteFile')->name('files.destroy');
    });
    Route::get('reports', [SponsorshipReportsController::class, 'index'])->name('reports');
    Route::get('reports/export', [SponsorshipReportsController::class, 'export'])->name('reports.export');
});
