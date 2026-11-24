<?php

namespace App\Modules\Finance\Console;

use App\Models\PaymobPayment;
use App\Modules\Finance\Domain\Enums\OnlineStatus;
use Illuminate\Console\Command;

/**
 * Checkouts the member never finished become "expired". A valid webhook that
 * arrives later still turns one into "paid" (RecordPaymobPayment).
 */
class ExpirePendingPaymentsCommand extends Command
{
    protected $signature = 'payments:expire-pending';

    protected $description = 'Mark online checkouts that passed their expiry time as expired';

    public function handle(): int
    {
        $count = PaymobPayment::where('status', OnlineStatus::Pending)
            ->where('expires_at', '<', now())
            ->update(['status' => OnlineStatus::Expired, 'updated_at' => now()]);

        $this->info("Expired {$count} checkout(s).");

        return self::SUCCESS;
    }
}
