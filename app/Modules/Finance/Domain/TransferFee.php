<?php

namespace App\Modules\Finance\Domain;

use App\Modules\Shared\Domain\Money;

/**
 * What the member pays on top of an online payment to cover Paymob's charge.
 *
 * Paymob takes a fixed part plus a percentage of the whole amount charged, so
 * the total is grossed up: after Paymob's cut the association still receives
 * the full amount. total = ceil((amount + fixed) / (1 - rate)).
 *
 * Only the amount itself is credited to the due; the fee is charged through
 * Paymob and never recorded against the member.
 */
final class TransferFee
{
    public function __construct(
        private readonly int $fixedPiasters,
        private readonly int $basisPoints,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (int) config('paymob.fee_fixed_piasters', 300),
            (int) config('paymob.fee_basis_points', 275),
        );
    }

    public function for(Money $amount): Money
    {
        if ($amount->isZero()) {
            return Money::zero();
        }

        $kept = 10000 - $this->basisPoints;
        $total = intdiv(($amount->piasters + $this->fixedPiasters) * 10000 + $kept - 1, $kept);

        return Money::cents($total - $amount->piasters);
    }
}
