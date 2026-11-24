<?php

namespace App\Modules\Cemeteries;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Regions, tombs, chambers and burials (merged in from the old adndan project). */
class CemeteriesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['web', 'auth'])->prefix('admin')->group(__DIR__.'/routes/web.php');
    }
}
