<?php

namespace App\Modules\Messaging\Application;

use App\Models\Reminders;
use App\Models\SMSMSGS;
use App\Models\SMSSubscribers;
use App\Modules\Messaging\Domain\SmsLength;
use App\Modules\Messaging\Domain\SmsSender;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Messages to SMS subscribers: staff announcements, and the automatic expiry
 * reminders run every night by the scheduler.
 */
class SmsCampaigns
{
    public function __construct(private readonly SmsSender $sender) {}

    /**
     * Sends a staff message to every active subscriber after checking the balance.
     *
     * @return array{success_count: int, failed_count: int, failed_numbers: list<string>}
     */
    public function announce(string $message): array
    {
        $numbers = SMSSubscribers::where('active_sms', 1)->pluck('mobile_no')->all();
        $needed = count($numbers) * SmsLength::parts($message);
        $balance = $this->sender->balance();

        if ($balance < $needed) {
            throw new DomainException("الرصيد لا يكفي، الرصيد الحالي: {$balance} رسالة، المطلوب: {$needed} رسالة");
        }

        $results = collect($this->sender->send($numbers, $message));
        SMSMSGS::create(['message' => $message, 'sent_status' => $results->every('success') ? 1 : 0]);
        $failed = $results->where('success', false)->values();

        return [
            'success_count' => $results->count() - $failed->count(),
            'failed_count' => $failed->count(),
            'failed_numbers' => $failed->pluck('number')->all(),
        ];
    }

    /** A month before expiry. */
    public function remindMonthBefore(CarbonImmutable $today): int
    {
        return $this->remind(
            $this->activeExpiringOn($today->addMonth()),
            'month_before',
            'العضو الكريم برجاء العلم ان اشتراك الرسائل الخاص بكم ينتهي بعد شهر. برجاء التجديد للاستمرار في الاستفادة من الخدمة.',
        );
    }

    /** A week before expiry. */
    public function remindWeekBefore(CarbonImmutable $today): int
    {
        return $this->remind(
            $this->activeExpiringOn($today->addWeek()),
            'week_before',
            'اشتراكك في خدمة الرسائل سينتهي خلال أسبوع. يرجى التجديد.',
        );
    }

    /** On the expiry day: the grace period starts now. */
    public function remindOnExpiry(CarbonImmutable $today): int
    {
        $grace = (int) config('adendan.sms.grace_days');

        return $this->remind(
            $this->activeExpiringOn($today),
            'expiry_day',
            "انتهى اشتراكك في خدمة الرسائل اليوم. لديك فترة سماح {$grace} يوماً للتجديد، بعدها سيتوقف الاشتراك تلقائياً.",
        );
    }

    /** After the grace period: switch the service off. @return int subscriptions switched off */
    public function deactivateExpired(CarbonImmutable $today): int
    {
        $lastValidExpiry = $today->subDays((int) config('adendan.sms.grace_days'));

        return SMSSubscribers::where('active_sms', 1)
            ->whereDate('subscription_expiry_date', '<', $lastValidExpiry->toDateString())
            ->update(['active_sms' => 0]);
    }

    private function activeExpiringOn(CarbonImmutable $date): Collection
    {
        return SMSSubscribers::where('active_sms', 1)->whereDate('subscription_expiry_date', $date->toDateString())->get();
    }

    private function remind(Collection $subscribers, string $type, string $message): int
    {
        if ($subscribers->isEmpty()) {
            return 0;
        }

        $this->sender->send($subscribers->pluck('mobile_no')->all(), $message);

        Reminders::insert($subscribers->map(fn (SMSSubscribers $subscriber) => [
            'subscriber_id' => $subscriber->id,
            'mobile_no' => $subscriber->mobile_no,
            'type' => $type,
            'message' => $message,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());

        return $subscribers->count();
    }
}
