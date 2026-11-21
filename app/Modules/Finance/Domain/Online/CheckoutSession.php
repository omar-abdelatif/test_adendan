<?php

namespace App\Modules\Finance\Domain\Online;

use JsonSerializable;

final class CheckoutSession implements JsonSerializable
{
    public function __construct(
        public readonly string $checkoutUrl,
        public readonly string $clientSecret,
        public readonly string $reference,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'checkout_url' => $this->checkoutUrl,
            'client_secret' => $this->clientSecret,
            'reference' => $this->reference,
        ];
    }
}
