<?php

namespace App\Modules\Finance\Domain;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The association's subscription year runs July to June and is named after the
 * year it ends in: July 2026 to June 2027 is subscription year 2027.
 */
final class FiscalCalendar
{
    public function subscriptionYear(CarbonInterface $date): int
    {
        return $date->month >= 7 ? $date->year + 1 : $date->year;
    }

    /**
     * Months left in the subscription year, counting the current month:
     * July = 12, November = 8, January = 6, June = 1.
     */
    public function remainingMonths(CarbonInterface $date): int
    {
        $month = CarbonImmutable::instance($date)->startOfMonth();
        $yearEnd = CarbonImmutable::create($this->subscriptionYear($date), 7, 1);

        return (int) $month->diffInMonths($yearEnd);
    }
}
