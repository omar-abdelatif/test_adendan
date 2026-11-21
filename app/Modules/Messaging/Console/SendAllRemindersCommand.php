<?php

namespace App\Modules\Messaging\Console;

use App\Modules\Messaging\Application\SmsCampaigns;
use Carbon\CarbonImmutable;

/** The dashboard's send-reminders button: every reminder due today. */
class SendAllRemindersCommand extends SmsReminderCommand
{
    protected $signature = 'subscriptions:notify-expiry';

    protected $description = 'All SMS expiry reminders due today';

    protected function process(SmsCampaigns $campaigns, CarbonImmutable $today): int
    {
        return $campaigns->remindMonthBefore($today) + $campaigns->remindWeekBefore($today) + $campaigns->remindOnExpiry($today);
    }
}
