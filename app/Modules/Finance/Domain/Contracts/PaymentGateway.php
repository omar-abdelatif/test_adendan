<?php

namespace App\Modules\Finance\Domain\Contracts;

use App\Modules\Finance\Domain\Online\CheckoutRequest;
use App\Modules\Finance\Domain\Online\CheckoutSession;

/**
 * An online payment provider. The app only knows this interface; Paymob is one
 * implementation (Infrastructure\Paymob\PaymobGateway).
 */
interface PaymentGateway
{
    public function createCheckout(CheckoutRequest $request): CheckoutSession;

    /**
     * Whether a server-to-server notification really came from the provider.
     *
     * @param  array<string, mixed>  $transaction
     */
    public function verifyCallback(array $transaction, ?string $signature): bool;
}
