<?php

namespace App\Modules\Sponsorship;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Sponsorship cases and their papers (merged in from the old adndan project). */
class SponsorshipServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['web', 'auth'])->prefix('admin')->group(__DIR__.'/routes/web.php');
    }
}
