<?php

namespace App\Modules\Members;

use App\Modules\Members\Console\IssueAppPasswordsCommand;
use App\Modules\Members\Console\ReplaceGuessablePasswordsCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MembersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([IssueAppPasswordsCommand::class, ReplaceGuessablePasswordsCommand::class]);
        }

        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['web', 'auth'])->prefix('admin')->group(__DIR__.'/routes/web.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/routes/api.php');
        Route::middleware('web')->group(__DIR__.'/routes/site.php');
    }
}
