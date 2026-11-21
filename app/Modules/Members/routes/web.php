<?php

/*
 * Members dashboard routes (prefix "admin", web + auth middleware).
 * URLs and route names are unchanged from the old routes/web.php.
 */

use App\Modules\Members\Http\Controllers\Admin\MemberReportsController;
use App\Modules\Members\Http\Controllers\Admin\MembersController;
use App\Modules\Members\Http\Controllers\Admin\ProfileChangesController;
use Illuminate\Support\Facades\Route;

Route::prefix('subscribers')->controller(MembersController::class)->group(function () {
    Route::get('subscribers/all', 'index')->name('subscriber.all');
    Route::get('subscribers/ajax/data/subscribers', 'table')->name('ajax.subscribers');
    Route::post('store', 'store')->name('subscribe.store');
    Route::get('update/{subscriber}', 'edit')->name('subscriber.details');
    Route::post('update', 'update')->name('subscribe.update');
    Route::delete('delete/{subscriber}', 'destroy')->name('subscribe.destroy');
    Route::post('bulk_upload', 'import')->name('subscriber.bulk');
    Route::get('get-year-cost/{year}', 'yearCost')->name('get.year.cost');
    Route::post('generate-passwords', 'issuePasswords')->name('generate-passwords');
});

Route::prefix('reports')->controller(MemberReportsController::class)->group(function () {
    Route::get('age', 'ages')->name('reports.age');
    Route::get('jobs', 'jobs')->name('reports.jobs');
    Route::get('location', 'locations')->name('reports.location');
    Route::get('incomplete', 'incomplete')->name('reports.incompete');
    Route::get('associates', 'associates')->name('reports.associates');
    Route::get('subscribers_data', 'pendingChanges')->name('reports.subscribersData');
});

Route::controller(ProfileChangesController::class)->group(function () {
    Route::post('reports/subscribers_data_ocr/approve/{scan}', 'approveOcr')->name('reports.search.approve');
    Route::post('searched_data/approve', 'approveWebsite')->name('search.approve');
    Route::delete('searched_data/delete/{request}', 'deleteWebsite')->name('search.delete');
    Route::delete('searched_data/delete_ocr/{scan}', 'deleteOcr')->name('search.ocr.delete');
    Route::get('users/update_requests', 'appRequests')->name('reports.updateRequests');
    Route::post('users/update_request', 'approveApp')->name('user.updateRequest');
    Route::delete('users/decline_update_request/{staging}', 'declineApp')->name('user.declineUpdateRequest');
});
