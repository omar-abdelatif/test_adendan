<?php

namespace App\Modules\Messaging\Console;

use App\Modules\Messaging\Application\SmsCampaigns;
use Carbon\CarbonImmutable;

class RemindMonthBeforeCommand extends SmsReminderCommand
{
    protected $signature = 'sms:notify-expiry-month';

    protected $description = 'SMS reminder one month before expiry';

    protected function process(SmsCampaigns $campaigns, CarbonImmutable $today): int
    {
        return $campaigns->remindMonthBefore($today);
    }
}
