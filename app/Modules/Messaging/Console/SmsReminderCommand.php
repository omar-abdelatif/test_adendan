<?php

namespace App\Modules\Messaging\Console;

use App\Modules\Messaging\Application\SmsCampaigns;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Nightly SMS jobs (scheduled in App\Console\Kernel). The command names are
 * the ones the scheduler already uses.
 */
abstract class SmsReminderCommand extends Command
{
    public function handle(SmsCampaigns $campaigns): int
    {
        $count = $this->process($campaigns, CarbonImmutable::today());
        $this->info("{$this->description}: {$count}");

        return self::SUCCESS;
    }

    abstract protected function process(SmsCampaigns $campaigns, CarbonImmutable $today): int;
}
