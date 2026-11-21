<?php

/*
 * Finance dashboard routes, loaded under prefix "admin" with the web + auth
 * middleware (see FinanceServiceProvider). URLs and route names are unchanged
 * from the old routes/web.php, so the views keep working.
 */

use App\Modules\Finance\Http\Controllers\Admin\CostYearsController;
use App\Modules\Finance\Http\Controllers\Admin\DonationsController;
use App\Modules\Finance\Http\Controllers\Admin\DonorsController;
use App\Modules\Finance\Http\Controllers\Admin\DuesController;
use App\Modules\Finance\Http\Controllers\Admin\ExpensesController;
use App\Modules\Finance\Http\Controllers\Admin\FinanceReportsController;
use App\Modules\Finance\Http\Controllers\Admin\MemberAccountController;
use App\Modules\Finance\Http\Controllers\Admin\OuterDonationsController;
use App\Modules\Finance\Http\Controllers\Admin\TransactionImportController;
use App\Modules\Finance\Http\Controllers\Admin\TransfersController;
use Illuminate\Support\Facades\Route;

Route::get('subscription/history/{subscriber}', [MemberAccountController::class, 'show'])->name('subscription.history');

Route::controller(DuesController::class)->group(function () {
    Route::post('subscription/dues/pay', 'pay')->name('subscription.pay');
    Route::post('subscription/dues/upload_delays', 'issueSubscriptionDues')->name('delays.costbyyear');
    Route::post('dues/subscriber_dues', 'import')->name('bulk_subscriber_dues');
});

Route::controller(DonationsController::class)->group(function () {
    Route::post('donations/donations/store', 'store')->name('donations.store');
    Route::post('donations/donations/delays/upload_bulk', 'issueDonationDues')->name('delays.uploadDonations');
});

Route::controller(OuterDonationsController::class)->group(function () {
    Route::get('outer_donations/outer_donations/history/{donator}', 'index')->name('outer_donations.history');
    Route::post('outer_donations/outer_donations/history/store', 'store')->name('outer_donations.store');
});

Route::controller(CostYearsController::class)->group(function () {
    Route::get('costyears/costyears/all', 'index')->name('costyears.all');
    Route::post('costyears/costyears/store', 'store')->name('costyears.store');
    Route::post('costyears/costyears/update', 'update')->name('costyears.update');
    Route::get('costyears/costyears/delete/{year}', 'destroy')->name('costyears.remove');
});

Route::controller(ExpensesController::class)->group(function () {
    Route::get('miscellaneous/miscellaneous/all', 'index')->name('miscellaneous.all');
    Route::post('miscellaneous/miscellaneous/store', 'store')->name('miscellaneous.store');
    Route::post('miscellaneous/miscellaneous/update', 'update')->name('miscellaneous.update');
    Route::get('miscellaneous/miscellaneous/delete/{expense}', 'destroy')->name('miscellaneous.delete');
});

Route::controller(TransfersController::class)->group(function () {
    Route::post('withdraw/withdraw', 'safeToBank')->name('withdraw');
    Route::post('withdraw/withdraw/bank', 'bankToSafe')->name('bank.withdraw');
});

Route::controller(FinanceReportsController::class)->group(function () {
    Route::get('reports/all', 'subscriptions')->name('reports.subscriptions');
    Route::get('reports/safe', 'safe')->name('reports.safe');
    Route::get('reports/bank', 'bank')->name('bankTransactios');
    Route::get('reports/search', 'oldDues')->name('reports.search');
    Route::get('reports/donations', 'outerDonations')->name('reports.donations');
    Route::get('reports/inner_donations', 'innerDonations')->name('reports.innerDonations');
});

Route::post('import-payment-transactions', TransactionImportController::class)->name('payment_transactions.import');

Route::prefix('donators')->controller(DonorsController::class)->group(function () {
    Route::get('donators/all', 'index')->name('donators.all');
    Route::post('donators/store', 'store')->name('donators.store');
    Route::post('donators/update', 'update')->name('donators.update');
    Route::get('donators/remove/{donor}', 'destroy')->name('donators.remove');
});
