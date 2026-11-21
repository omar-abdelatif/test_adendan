<?php

namespace App\Modules\Finance\Application\Actions;

use App\Models\Donations;
use App\Models\Due;
use App\Models\PaymentTransaction;
use App\Models\Subscribers;
use App\Modules\Finance\Application\Ledger;
use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Channel;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\Enums\DueType;
use App\Modules\Finance\Domain\Enums\PaymentCategory;
use App\Modules\Finance\Domain\LedgerEntry;
use App\Modules\Shared\Domain\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Pays all or part of a member's due (subscription or donation), at the office
 * or online, and records it in the ledger.
 *
 * Paying more than what is left settles the due and records only what was owed
 * at the office; online, the provider already took the money, so the full
 * amount is recorded.
 */
class PayDue
{
    public function __construct(private readonly Ledger $ledger) {}

    /**
     * @param  array{paymob_order_id?: ?string, paymob_transaction_id?: ?string}  $online
     */
    public function handle(
        Due $due,
        Money $amount,
        CarbonInterface $date,
        string $paymentMethod,
        Channel $channel = Channel::Office,
        int|string|null $invoiceNo = null,
        array $online = [],
    ): PaymentTransaction {
        return DB::transaction(function () use ($due, $amount, $date, $paymentMethod, $channel, $invoiceNo, $online) {
            $due = Due::query()->lockForUpdate()->findOrFail($due->id);

            $remaining = Money::pounds($due->amount_remaining ?? $due->total_amount);
            $isOnline = $channel === Channel::Online;
            $recorded = $isOnline ? $amount : $amount->min($remaining);
            $category = $amount->greaterThanOrEqual($remaining) ? PaymentCategory::Full : PaymentCategory::Partial;
            $type = $this->dueType($due);

            $transaction = $this->ledger->record(new LedgerEntry(
                type: $type->transactionType(),
                direction: Direction::In,
                amount: $recorded,
                item: $due->item,
                account: $isOnline ? null : Account::Safe,
                memberId: (int) $due->member_id,
                category: $category,
                paymentMethod: $paymentMethod,
                channel: $channel,
                invoiceNo: $invoiceNo,
                date: $date,
                paymobOrderId: $online['paymob_order_id'] ?? null,
                paymobTransactionId: $online['paymob_transaction_id'] ?? null,
                paymobStatus: $isOnline ? 'paid' : null,
            ));

            if ($category === PaymentCategory::Full) {
                $due->delete();
            } else {
                $due->update([
                    'amount_paid' => Money::pounds($due->amount_paid)->plus($recorded)->toPounds(),
                    'amount_remaining' => $remaining->minusFloor($recorded)->toPounds(),
                ]);
            }

            if ($type === DueType::Donation) {
                $this->recordDonation($due, $recorded, $date, $invoiceNo);
            }

            return $transaction;
        });
    }

    private function dueType(Due $due): DueType
    {
        return DueType::tryFrom((string) $due->transaction_type)
            ?? (str_contains((string) $due->item, 'تبرع') ? DueType::Donation : DueType::Subscription);
    }

    /** The inner-donations report reads the donations table, so paid donation dues appear there too. */
    private function recordDonation(Due $due, Money $amount, CarbonInterface $date, int|string|null $invoiceNo): void
    {
        $member = Subscribers::where('member_id', $due->member_id)->first();
        if (! $member) {
            return;
        }

        Donations::create([
            'member_id' => $member->member_id,
            'subscribers_id' => $member->id,
            'invoice_no' => is_numeric($invoiceNo) ? (int) $invoiceNo : null,
            'amount' => $amount->toPounds(),
            'donation_type' => 'مادي',
            'donation_category' => $due->item,
            'payment_date' => $date->toDateString(),
        ]);
    }
}
