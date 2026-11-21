<?php

namespace App\Modules\Finance\Application;

use App\Models\TotalBank;
use App\Models\TotalSafe;
use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Exceptions\InsufficientFunds;
use App\Modules\Shared\Domain\Money;
use Illuminate\Support\Facades\DB;

/**
 * The running balances of the safe (total_safes) and the bank (total_banks).
 *
 * Every change locks the balance row, so two cashiers saving at the same moment
 * cannot overwrite each other's update. Call inside a DB transaction (Ledger does).
 */
class Treasury
{
    public function balance(Account $account): Money
    {
        return Money::pounds($this->row($account, lock: false)->amount);
    }

    public function credit(Account $account, Money $amount): void
    {
        DB::transaction(function () use ($account, $amount) {
            $row = $this->row($account, lock: true);
            $row->update(['amount' => Money::pounds($row->amount)->plus($amount)->toPounds()]);
        });
    }

    /**
     * @throws InsufficientFunds when $allowOverdraft is false and the balance is too low
     */
    public function debit(Account $account, Money $amount, bool $allowOverdraft = false): void
    {
        DB::transaction(function () use ($account, $amount, $allowOverdraft) {
            $row = $this->row($account, lock: true);
            $current = (int) $row->amount;

            if (! $allowOverdraft && $amount->toPounds() > $current) {
                throw new InsufficientFunds($account, Money::pounds(max(0, $current)), $amount);
            }

            $row->update(['amount' => $current - $amount->toPounds()]);
        });
    }

    private function row(Account $account, bool $lock): TotalSafe|TotalBank
    {
        $model = match ($account) {
            Account::Safe => TotalSafe::class,
            Account::Bank => TotalBank::class,
        };

        $query = $model::query()->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first() ?? $model::create(['amount' => 0]);
    }
}
