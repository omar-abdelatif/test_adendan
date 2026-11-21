<?php

namespace App\Modules\Messaging\Application;

use App\Models\SMSFEES;
use App\Models\SMSSubscribers;
use App\Modules\Shared\Domain\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The paid SMS service. A season ends on June 30: subscribing between January
 * and June runs to this June 30, between July and December to next June 30.
 * Paying while a season is still active buys the following season.
 *
 * One row per member (or per mobile number for non-members): paying again
 * updates it instead of adding a duplicate.
 */
class SmsSubscriptions
{
    public function fee(): Money
    {
        return Money::pounds(SMSFEES::query()->orderBy('id')->value('amount'));
    }

    public function seasonEnd(CarbonInterface $date): CarbonImmutable
    {
        $year = $date->month <= 6 ? $date->year : $date->year + 1;

        return CarbonImmutable::create($year, 6, 30)->endOfDay();
    }

    public function activate(?int $memberId, string $mobile, CarbonInterface $paidAt): SMSSubscribers
    {
        $mobileDigits = (int) preg_replace('/\D/', '', $mobile);

        $subscription = SMSSubscribers::query()
            ->when($memberId, fn ($q) => $q->where('member_id', $memberId), fn ($q) => $q->whereNull('member_id')->where('mobile_no', $mobileDigits))
            ->orderByDesc('id')
            ->first() ?? new SMSSubscribers(['member_id' => $memberId]);

        $expiry = $this->seasonEnd($paidAt);
        $currentExpiry = $subscription->subscription_expiry_date ? CarbonImmutable::parse($subscription->subscription_expiry_date) : null;
        if ($subscription->active_sms && $currentExpiry && $currentExpiry->greaterThanOrEqualTo($expiry->startOfDay())) {
            $expiry = $expiry->addYear();
        }

        $subscription->fill([
            'mobile_no' => $mobileDigits ?: $subscription->mobile_no,
            'amount' => $this->fee()->toPounds(),
            'subscription_start_date' => $paidAt->toDateString(),
            'subscription_expiry_date' => $expiry->toDateString(),
            'active_sms' => 1,
        ])->save();

        return $subscription;
    }
}
