<?php

namespace App\Modules\Finance\Application\Actions;

use App\Models\Due;
use App\Models\Subscribers;
use App\Modules\Finance\Domain\Enums\DueType;
use App\Modules\Finance\Domain\Exceptions\FinanceException;
use App\Modules\Shared\Domain\Money;
use Illuminate\Support\Facades\DB;

/**
 * Charges every active member a due: the yearly subscription, or a donation
 * campaign decided by the board. Resigned members (status 2) are skipped.
 */
class IssueDues
{
    private const RESIGNED = 2;

    /** @return int how many members were charged */
    public function handle(DueType $type, string $item, Money $amount): int
    {
        $item = trim($item);

        if (Due::where('item', $item)->where('transaction_type', $type->value)->exists()) {
            throw FinanceException::duesAlreadyIssued($item);
        }

        return DB::transaction(function () use ($type, $item, $amount) {
            $count = 0;
            $now = now();

            Subscribers::query()
                ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', self::RESIGNED))
                ->select(['id', 'member_id']) // chunkById pages on id
                ->chunkById(500, function ($members) use ($type, $item, $amount, $now, &$count) {
                    Due::insert($members->map(fn ($member) => [
                        'member_id' => $member->member_id,
                        'item' => $item,
                        'total_amount' => $amount->toPounds(),
                        'amount_paid' => 0,
                        'amount_remaining' => $amount->toPounds(),
                        'transaction_type' => $type->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());
                    $count += $members->count();
                });

            return $count;
        });
    }
}
