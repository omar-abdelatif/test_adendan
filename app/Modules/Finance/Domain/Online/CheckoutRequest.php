<?php

namespace App\Modules\Finance\Domain\Online;

use App\Modules\Shared\Domain\Money;

/** What the server asks the provider to charge. The amount always comes from our records. */
final class CheckoutRequest
{
    /** @param array<string, scalar|null> $extras sent back by the provider untouched */
    public function __construct(
        public readonly string $reference,
        public readonly Money $amount,
        public readonly string $description,
        public readonly string $method,
        public readonly string $customerName,
        public readonly string $customerPhone,
        public readonly array $extras = [],
    ) {}
}
