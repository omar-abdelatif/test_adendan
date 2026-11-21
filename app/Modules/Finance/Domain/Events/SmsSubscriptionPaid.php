<?php

namespace App\Modules\Finance\Domain\Events;

use Carbon\CarbonInterface;
use Illuminate\Foundation\Events\Dispatchable;

/** A member paid for the SMS service online; Messaging activates it. */
final class SmsSubscriptionPaid
{
    use Dispatchable;

    public function __construct(
        public readonly int $memberId,
        public readonly string $mobile,
        public readonly CarbonInterface $paidAt,
    ) {}
}
