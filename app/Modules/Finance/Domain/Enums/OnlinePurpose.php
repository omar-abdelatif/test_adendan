<?php

namespace App\Modules\Finance\Domain\Enums;

/** What a member pays for from the mobile app. */
enum OnlinePurpose: string
{
    case Subscription = 'subscription';
    case Donation = 'donation';
    case Sms = 'sms';

    /** The dues.transaction_type rows this purpose pays, if any. */
    public function dueType(): ?DueType
    {
        return match ($this) {
            self::Subscription => DueType::Subscription,
            self::Donation => DueType::Donation,
            self::Sms => null,
        };
    }
}
