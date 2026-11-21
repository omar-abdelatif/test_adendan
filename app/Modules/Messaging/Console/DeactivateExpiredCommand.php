<?php

namespace App\Modules\Messaging\Console;

use App\Modules\Messaging\Application\SmsCampaigns;
use Carbon\CarbonImmutable;

class DeactivateExpiredCommand extends SmsReminderCommand
{
    protected $signature = 'subscriptions:check-expiry';

    protected $description = 'Switch off SMS subscriptions past the grace period';

    protected function process(SmsCampaigns $campaigns, CarbonImmutable $today): int
    {
        return $campaigns->deactivateExpired($today);
    }
}
