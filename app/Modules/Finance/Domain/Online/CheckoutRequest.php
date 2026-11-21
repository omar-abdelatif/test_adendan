<?php

namespace App\Modules\Finance\Domain\Online;

use App\Modules\Shared\Domain\Money;

final class CheckoutRequest
{
    public function __construct(
        public readonly OrderReference $reference,
        public readonly Money $amount,
        public readonly string $description,
        public readonly string $method,
        public readonly string $customerName,
        public readonly string $customerPhone,
    ) {}
}
