<?php

namespace App\Modules\Finance\Application;

use App\Models\PaymentTransaction;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Writes money movements to payment_transactions and moves the matching account
 * balance in the same database transaction, so the safe and bank totals never
 * drift from the entries behind them.
 */
class Ledger
{
    public function __construct(private readonly Treasury $treasury) {}

    public function record(LedgerEntry $entry, bool $allowOverdraft = false): PaymentTransaction
    {
        if ($entry->amount->hasFraction()) {
            // The amount columns hold whole pounds; keep a trace when rounding happens.
            Log::warning('Ledger amount rounded to whole pounds', ['amount' => (string) $entry->amount, 'item' => $entry->item]);
        }

        return DB::transaction(function () use ($entry, $allowOverdraft) {
            if ($entry->account !== null) {
                $entry->direction === Direction::In
                    ? $this->treasury->credit($entry->account, $entry->amount, $entry->fund)
                    : $this->treasury->debit($entry->account, $entry->amount, $allowOverdraft, $entry->fund);
            }

            return PaymentTransaction::create([
                'fund' => $entry->fund->value,
                'member_id' => $entry->memberId,
                'amount' => $entry->amount->toPounds(),
                'item' => $entry->item,
                'inv_no' => $entry->invoiceNo,
                'payment_date' => ($entry->date ?? now())->toDateString(),
                'payment_method' => $entry->paymentMethod,
                'payment_cat' => $entry->category->value,
                'transaction_type' => $entry->type->value,
                'transaction_cat' => $entry->direction->value,
                'transaction_method' => $entry->channel->value,
                'account' => $entry->account?->value,
                'proof_img' => $entry->proofImage,
                'paymob_intention_id' => $entry->paymobOrderId,
                'paymob_transaction_id' => $entry->paymobTransactionId,
                'paymob_status' => $entry->paymobStatus,
            ]);
        });
    }
}
