<?php

namespace App\Modules\Finance\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Paymob confirmed a member's payment and it is recorded; Messaging tells the member. */
final class OnlinePaymentReceived
{
    use Dispatchable;

    public function __construct(
        public readonly int $memberId,
        public readonly float $amount,
        public readonly string $item,
    ) {}
}
