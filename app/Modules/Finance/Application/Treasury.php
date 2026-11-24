<?php

namespace App\Modules\Finance\Application;

use App\Models\TotalBank;
use App\Models\TotalSafe;
use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Fund;
use App\Modules\Finance\Domain\Exceptions\InsufficientFunds;
use App\Modules\Shared\Domain\Money;
use Illuminate\Support\Facades\DB;

/**
 * The running balances of the safe (total_safes) and the bank (total_banks),
 * one row per fund: the association's money, sponsorship, and the new tombs.
 *
 * Every change locks the balance row, so two cashiers saving at the same moment
 * cannot overwrite each other's update. Call inside a DB transaction (Ledger does).
 */
class Treasury
{
    public function balance(Account $account, Fund $fund = Fund::General): Money
    {
        return Money::pounds($this->row($account, $fund, lock: false)->amount);
    }

    public function credit(Account $account, Money $amount, Fund $fund = Fund::General): void
    {
        DB::transaction(function () use ($account, $amount, $fund) {
            $row = $this->row($account, $fund, lock: true);
            $row->update(['amount' => Money::pounds($row->amount)->plus($amount)->toPounds()]);
        });
    }

    /**
     * @throws InsufficientFunds when $allowOverdraft is false and the fund's balance is too low
     */
    public function debit(Account $account, Money $amount, bool $allowOverdraft = false, Fund $fund = Fund::General): void
    {
        DB::transaction(function () use ($account, $amount, $allowOverdraft, $fund) {
            $row = $this->row($account, $fund, lock: true);
            $current = (int) $row->amount;

            if (! $allowOverdraft && $amount->toPounds() > $current) {
                throw new InsufficientFunds($account, Money::pounds(max(0, $current)), $amount, $fund);
            }

            $row->update(['amount' => $current - $amount->toPounds()]);
        });
    }

    private function row(Account $account, Fund $fund, bool $lock): TotalSafe|TotalBank
    {
        $model = match ($account) {
            Account::Safe => TotalSafe::class,
            Account::Bank => TotalBank::class,
        };

        $query = $model::query()->where('fund', $fund->value)->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first() ?? $model::create(['fund' => $fund->value, 'amount' => 0]);
    }
}
