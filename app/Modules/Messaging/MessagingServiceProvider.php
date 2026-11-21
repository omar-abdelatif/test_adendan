<?php

namespace App\Modules\Messaging;

use App\Modules\Finance\Domain\Events\SmsSubscriptionPaid;
use App\Modules\Messaging\Domain\SmsSender;
use App\Modules\Messaging\Infrastructure\EgyptLinxSender;
use App\Modules\Messaging\Listeners\ActivatePaidSmsSubscription;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsSender::class, fn () => new EgyptLinxSender(config('services.egyptlinx', [])));
    }

    public function boot(): void
    {
        Event::listen(SmsSubscriptionPaid::class, ActivatePaidSmsSubscription::class);
    }
}
