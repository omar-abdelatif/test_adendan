<?php

namespace App\Modules\Finance\Domain;

use App\Modules\Shared\Domain\Money;

/** The fees and starting dues of a new membership, from config/adendan.php. */
final class FeeSchedule
{
    /**
     * @param  list<array{item: string, amount: int}>  $newMemberDues
     */
    public function __construct(
        private readonly int $joiningFee,
        private readonly int $annualDonation,
        private readonly array $newMemberDues,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (int) config('adendan.fees.joining'),
            (int) config('adendan.fees.annual_donation'),
            config('adendan.fees.new_member_dues', []),
        );
    }

    public function joiningFee(): Money
    {
        return Money::pounds($this->joiningFee);
    }

    public function annualDonation(): Money
    {
        return Money::pounds($this->annualDonation);
    }

    /** The share of a yearly subscription for the months left, plus the joining fee. */
    public function firstSubscription(Money $yearlyCost, int $remainingMonths): Money
    {
        $prorated = Money::pounds($yearlyCost->piasters / 100 / 12 * $remainingMonths);

        return $prorated->plus($this->joiningFee());
    }

    /** @return list<array{item: string, amount: Money}> */
    public function newMemberDues(): array
    {
        return array_map(
            fn (array $due) => ['item' => $due['item'], 'amount' => Money::pounds($due['amount'])],
            $this->newMemberDues,
        );
    }
}
