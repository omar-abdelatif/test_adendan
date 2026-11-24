<?php

namespace App\Modules\Finance\Domain\Online;

/** A checkout page opened at the provider. */
final class CheckoutSession
{
    public function __construct(
        public readonly string $checkoutUrl,
        public readonly ?string $providerOrderId,
    ) {}
}
