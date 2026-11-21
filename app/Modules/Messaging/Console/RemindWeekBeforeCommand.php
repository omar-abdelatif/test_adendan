<?php

namespace App\Modules\Messaging\Console;

use App\Modules\Messaging\Application\SmsCampaigns;
use Carbon\CarbonImmutable;

/** Was a copy of the month reminder (members got it twice); now one week before. */
class RemindWeekBeforeCommand extends SmsReminderCommand
{
    protected $signature = 'sms:notify-expiry-upcoming';

    protected $description = 'SMS reminder one week before expiry';

    protected function process(SmsCampaigns $campaigns, CarbonImmutable $today): int
    {
        return $campaigns->remindWeekBefore($today);
    }
}
