<?php

namespace App\Modules\Access;

use App\Modules\Access\Domain\Permissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AccessServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The master role passes every check, including permissions added later.
        Gate::before(fn ($user) => method_exists($user, 'hasRole') && $user->hasRole(Permissions::SUPER_ROLE) ? true : null);

        if (! $this->app->routesAreCached()) {
            Route::middleware(['web', 'auth'])->prefix('admin')->group(__DIR__.'/routes/web.php');
        }
    }
}
