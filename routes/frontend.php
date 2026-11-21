<?php

/*
 * Static website pages. Pages with data are registered by the Content,
 * Members and Directory modules.
 */

use Illuminate\Support\Facades\Route;

Route::name('site.')->group(function () {
    Route::view('about', 'frontend.pages.about')->name('about');
    Route::view('contact_us', 'frontend.pages.contact')->name('contact');
    Route::view('donate_page', 'frontend.pages.donate')->name('donate');
});
