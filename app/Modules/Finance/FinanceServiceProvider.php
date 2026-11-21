<?php

namespace App\Modules\Finance;

use App\Modules\Finance\Domain\Contracts\PaymentGateway;
use App\Modules\Finance\Domain\FeeSchedule;
use App\Modules\Finance\Domain\FiscalCalendar;
use App\Modules\Finance\Infrastructure\Paymob\PaymobGateway;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FiscalCalendar::class);
        $this->app->bind(FeeSchedule::class, fn () => FeeSchedule::fromConfig());
        $this->app->bind(PaymentGateway::class, fn () => new PaymobGateway(config('services.paymob', [])));
    }

    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['web', 'auth'])->prefix('admin')->group(__DIR__.'/routes/web.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/routes/api.php');
    }
}
