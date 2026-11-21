<?php

namespace Tests\Unit\Finance;

use App\Modules\Finance\Domain\FeeSchedule;
use App\Modules\Finance\Domain\FiscalCalendar;
use App\Modules\Shared\Domain\Money;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FiscalCalendarTest extends TestCase
{
    public static function dates(): array
    {
        // date, subscription year, months left including the current one
        return [
            'first day of the year (July)' => ['2026-07-01', 2027, 12],
            'November' => ['2026-11-21', 2027, 8],
            'January' => ['2027-01-10', 2027, 6],
            'last day of June' => ['2027-06-30', 2027, 1],
        ];
    }

    #[DataProvider('dates')]
    public function test_subscription_year_and_remaining_months(string $date, int $year, int $months): void
    {
        $calendar = new FiscalCalendar;
        $day = CarbonImmutable::parse($date.' 15:00');

        $this->assertSame($year, $calendar->subscriptionYear($day));
        $this->assertSame($months, $calendar->remainingMonths($day));
    }

    public function test_first_subscription_is_prorated_plus_joining_fee(): void
    {
        $fees = new FeeSchedule(joiningFee: 50, annualDonation: 40, newMemberDues: [['item' => 'تبرع', 'amount' => 100]]);

        // 360 a year, 8 months left: 240 + 50
        $this->assertSame(290, $fees->firstSubscription(Money::pounds(360), 8)->toPounds());
        $this->assertSame(40, $fees->annualDonation()->toPounds());
        $this->assertSame(100, $fees->newMemberDues()[0]['amount']->toPounds());
    }
}
