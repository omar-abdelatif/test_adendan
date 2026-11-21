<?php

/*
 * Staff accounts, roles, permissions and the dashboard home
 * (prefix "admin", web + auth). URLs and names unchanged.
 */

use App\Modules\Access\Http\Controllers\DashboardController;
use App\Modules\Access\Http\Controllers\PermissionsController;
use App\Modules\Access\Http\Controllers\ProfileController;
use App\Modules\Access\Http\Controllers\RolesController;
use App\Modules\Access\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard/main', [DashboardController::class, 'home'])->name('home');
Route::get('activiy/activity', [DashboardController::class, 'activity'])->name('activity.index');

Route::prefix('users')->group(function () {
    // Every staff member manages their own profile.
    Route::get('profile', [ProfileController::class, 'show'])->name('user.profile');
    Route::post('update_profile', [ProfileController::class, 'update'])->name('user.update');

    Route::controller(StaffController::class)->group(function () {
        Route::get('users/all', 'index')->name('user.index');
        Route::post('user/store', 'store')->name('user.store');
        Route::post('user/update', 'update')->name('newuser.update');
        Route::get('user/delete/{user}', 'destroy')->name('user.delete');
    });
});

Route::prefix('roles')->name('roles.')->controller(RolesController::class)->group(function () {
    Route::get('roles', 'index')->name('index');
    Route::post('store', 'store')->name('store');
    Route::post('update', 'update')->name('update');
    Route::get('delete/{role}', 'destroy')->name('destroy');
});

Route::prefix('permissions')->name('permissions.')->controller(PermissionsController::class)->group(function () {
    Route::get('permissions', 'index')->name('index');
    Route::post('store', 'store')->name('store');
    Route::post('update', 'update')->name('update');
    Route::get('delete/{permission}', 'destroy')->name('destroy');
});
