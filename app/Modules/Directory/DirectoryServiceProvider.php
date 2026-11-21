<?php

namespace App\Modules\Directory;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DirectoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['web', 'auth'])->prefix('admin')->group(__DIR__.'/routes/web.php');
        Route::group([], __DIR__.'/routes/public.php');
    }
}
