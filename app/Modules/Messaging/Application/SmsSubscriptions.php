<?php

namespace App\Modules\Messaging\Application;

use App\Models\SMSFEES;
use App\Models\SMSSubscribers;
use App\Modules\Finance\Application\Ledger;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\Enums\TransactionType;
use App\Modules\Finance\Domain\LedgerEntry;
use App\Modules\Finance\Domain\Online\OrderReference;
use App\Modules\Shared\Domain\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

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
    public function __construct(private readonly Ledger $ledger) {}

    public function fee(): Money
    {
        return Money::pounds(SMSFEES::query()->orderBy('id')->value('amount'));
    }

    public function updateFee(Money $fee): void
    {
        $row = SMSFEES::query()->orderBy('id')->first() ?? new SMSFEES(['item' => OrderReference::SMS_ITEM]);
        $row->fill(['amount' => $fee->toPounds()])->save();
    }

    public function seasonEnd(CarbonInterface $date): CarbonImmutable
    {
        $year = $date->month <= 6 ? $date->year : $date->year + 1;

        return CarbonImmutable::create($year, 6, 30)->endOfDay();
    }

    /** Turns the service on (online payment already recorded by Finance). */
    public function activate(?int $memberId, string $mobile, CarbonInterface $paidAt): SMSSubscribers
    {
        $mobileDigits = (int) preg_replace('/\D/', '', $mobile);

        $subscription = SMSSubscribers::query()
            ->when($memberId, fn ($q) => $q->where('member_id', $memberId), fn ($q) => $q->whereNull('member_id')->where('mobile_no', $mobileDigits))
            ->orderByDesc('id')
            ->first() ?? new SMSSubscribers(['member_id' => $memberId]);

        return $this->extend($subscription, $mobileDigits, $paidAt);
    }

    /** Cash paid at the office for a new subscription: activate it and put the fee in the safe. */
    public function subscribeAtOffice(?int $memberId, string $mobile): SMSSubscribers
    {
        return DB::transaction(function () use ($memberId, $mobile) {
            $subscription = $this->activate($memberId, $mobile, now());
            $this->recordCash($subscription, TransactionType::SmsSubscription);

            return $subscription;
        });
    }

    /** Cash paid at the office to renew an existing subscription. */
    public function renewAtOffice(SMSSubscribers $subscription): SMSSubscribers
    {
        return DB::transaction(function () use ($subscription) {
            $this->extend($subscription, (int) $subscription->mobile_no, now());
            $this->recordCash($subscription, TransactionType::SmsRenewal);

            return $subscription;
        });
    }

    private function extend(SMSSubscribers $subscription, int $mobileDigits, CarbonInterface $paidAt): SMSSubscribers
    {
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

    private function recordCash(SMSSubscribers $subscription, TransactionType $type): void
    {
        $this->ledger->record(new LedgerEntry(
            type: $type,
            direction: Direction::In,
            amount: $this->fee(),
            item: OrderReference::SMS_ITEM,
            memberId: $subscription->member_id ? (int) $subscription->member_id : null,
        ));
    }
}
