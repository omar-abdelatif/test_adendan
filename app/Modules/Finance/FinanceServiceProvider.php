<?php

namespace App\Modules\Finance;

use App\Modules\Finance\Domain\FeeSchedule;
use App\Modules\Finance\Domain\FiscalCalendar;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FiscalCalendar::class);
        $this->app->bind(FeeSchedule::class, fn () => FeeSchedule::fromConfig());
    }
}
