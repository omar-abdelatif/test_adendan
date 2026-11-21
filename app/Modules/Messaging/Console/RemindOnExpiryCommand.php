<?php

namespace App\Modules\Messaging\Console;

use App\Modules\Messaging\Application\SmsCampaigns;
use Carbon\CarbonImmutable;

/** Was sent when the grace period ended; now on the expiry day, when it starts. */
class RemindOnExpiryCommand extends SmsReminderCommand
{
    protected $signature = 'sms:notify-expiry-today';

    protected $description = 'SMS on the expiry day (grace period starts)';

    protected function process(SmsCampaigns $campaigns, CarbonImmutable $today): int
    {
        return $campaigns->remindOnExpiry($today);
    }
}
