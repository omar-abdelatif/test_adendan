<?php

namespace App\Modules\Messaging\Listeners;

use App\Models\Subscribers;
use App\Modules\Finance\Domain\Events\OnlinePaymentReceived;
use App\Modules\Messaging\Domain\PushNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Push notification to the member once their online payment is recorded.
 * Queued, so Paymob's webhook gets its answer without waiting for Firebase.
 */
class NotifyMemberOfPayment implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly PushNotifier $push) {}

    public function handle(OnlinePaymentReceived $event): void
    {
        $member = Subscribers::where('member_id', $event->memberId)->first();
        if (! $member?->fcm_token) {
            return;
        }

        $amount = rtrim(rtrim(number_format($event->amount, 2, '.', ''), '0'), '.');

        try {
            $delivered = $this->push->send(
                $member->fcm_token,
                'تم استلام دفعتك بنجاح',
                "{$amount} جنيه - {$event->item}",
                ['type' => 'payment_received'],
            );
        } catch (Throwable $e) {
            // The payment is already recorded; a failed notification must never undo or block that.
            report($e);

            return;
        }

        if (! $delivered) {
            $member->forceFill(['fcm_token' => null])->save();
        }
    }
}
