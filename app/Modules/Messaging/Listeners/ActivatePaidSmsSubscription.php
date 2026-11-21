<?php

namespace App\Modules\Messaging\Listeners;

use App\Modules\Finance\Domain\Events\SmsSubscriptionPaid;
use App\Modules\Messaging\Application\SmsSubscriptions;

/** Turns the SMS service on once Finance has recorded the online payment. */
class ActivatePaidSmsSubscription
{
    public function __construct(private readonly SmsSubscriptions $subscriptions) {}

    public function handle(SmsSubscriptionPaid $event): void
    {
        $this->subscriptions->activate($event->memberId, $event->mobile, $event->paidAt);
    }
}
