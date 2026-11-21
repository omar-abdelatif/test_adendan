<?php

namespace App\Modules\Messaging;

use App\Modules\Finance\Domain\Events\SmsSubscriptionPaid;
use App\Modules\Messaging\Console\DeactivateExpiredCommand;
use App\Modules\Messaging\Console\RemindMonthBeforeCommand;
use App\Modules\Messaging\Console\RemindOnExpiryCommand;
use App\Modules\Messaging\Console\RemindWeekBeforeCommand;
use App\Modules\Messaging\Console\SendAllRemindersCommand;
use App\Modules\Messaging\Domain\PushNotifier;
use App\Modules\Messaging\Domain\SmsSender;
use App\Modules\Messaging\Infrastructure\EgyptLinxSender;
use App\Modules\Messaging\Infrastructure\FcmPushNotifier;
use App\Modules\Messaging\Listeners\ActivatePaidSmsSubscription;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsSender::class, fn () => new EgyptLinxSender(config('services.egyptlinx', [])));
        $this->app->bind(PushNotifier::class, fn () => new FcmPushNotifier(config('services.firebase', [])));
    }

    public function boot(): void
    {
        Event::listen(SmsSubscriptionPaid::class, ActivatePaidSmsSubscription::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                RemindMonthBeforeCommand::class,
                RemindWeekBeforeCommand::class,
                RemindOnExpiryCommand::class,
                DeactivateExpiredCommand::class,
                SendAllRemindersCommand::class,
            ]);
        }

        if (! $this->app->routesAreCached()) {
            Route::middleware(['web', 'auth'])->prefix('admin')->group(__DIR__.'/routes/web.php');
            Route::middleware('api')->prefix('api')->group(__DIR__.'/routes/api.php');
        }
    }
}
