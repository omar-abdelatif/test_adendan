<?php

namespace App\Modules\Finance\Application\Actions;

use App\Models\Donations;
use App\Models\Due;
use App\Models\PaymentTransaction;
use App\Models\PaymobPayment;
use App\Models\Subscribers;
use App\Modules\Finance\Application\Ledger;
use App\Modules\Finance\Domain\Enums\Channel;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\Enums\OnlinePurpose;
use App\Modules\Finance\Domain\Enums\OnlineStatus;
use App\Modules\Finance\Domain\Enums\TransactionType;
use App\Modules\Finance\Domain\Events\OnlinePaymentReceived;
use App\Modules\Finance\Domain\Events\SmsSubscriptionPaid;
use App\Modules\Finance\Domain\LedgerEntry;
use App\Modules\Shared\Domain\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies a verified Paymob "transaction processed" callback to the checkout
 * it belongs to. The only code path that turns a PaymobPayment into "paid".
 *
 * A paid payment is recorded exactly like a payment at the office (PayDue),
 * so it shows in the member's history and the due's remaining amount drops.
 * Safe to receive the same callback any number of times.
 */
class RecordPaymobPayment
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly PayDue $payDue,
    ) {}

    /**
     * @param  array<string, mixed>  $transaction  the callback's verified `obj`
     * @return bool false when no checkout matches the callback
     */
    public function handle(array $transaction): bool
    {
        $paidNow = null;

        $found = DB::transaction(function () use ($transaction, &$paidNow) {
            $payment = $this->find($transaction);
            if (! $payment) {
                return false;
            }

            $transactionId = (string) ($transaction['id'] ?? '');
            if ($payment->status === OnlineStatus::Paid || $payment->paymob_transaction_id !== null) {
                return true; // a replay
            }

            $cents = (int) ($transaction['amount_cents'] ?? -1);
            $currency = (string) ($transaction['currency'] ?? '');
            // Paymob charged the amount plus the fee; only the amount is credited below.
            if ($cents !== $payment->chargedCents() || $currency !== $payment->currency) {
                Log::error('Paymob amount mismatch', [
                    'payment_id' => $payment->public_id,
                    'expected_cents' => $payment->chargedCents(),
                    'received_cents' => $cents,
                ]);
                $payment->update(['status' => OnlineStatus::Failed, 'failure_reason' => 'amount_mismatch']);

                return true;
            }

            $success = ($transaction['success'] ?? null) === true;
            $pending = ($transaction['pending'] ?? null) === true;

            if ($success && ! $pending) {
                $receipt = $this->record($payment, $transaction);

                // A late webhook still wins over "expired": the money moved, so record it.
                $payment->update([
                    'status' => OnlineStatus::Paid,
                    'paid_at' => now(),
                    'paymob_transaction_id' => $transactionId !== '' ? $transactionId : null,
                    'receipt_inv_no' => (string) $receipt->inv_no,
                    'callback_payload' => $transaction,
                ]);
                $paidNow = $payment;
            } elseif (! $success && ! $pending) {
                $payment->update([
                    'status' => OnlineStatus::Failed,
                    'failure_reason' => mb_substr((string) (data_get($transaction, 'data.message') ?: 'declined'), 0, 250),
                ]);
            }

            return true;
        });

        if ($paidNow) {
            OnlinePaymentReceived::dispatch($paidNow->member_id, $paidNow->amountInPounds(), $this->label($paidNow));
        }

        return $found;
    }

    /** @param array<string, mixed> $transaction */
    private function find(array $transaction): ?PaymobPayment
    {
        $orderId = data_get($transaction, 'order.id');
        $reference = (string) (data_get($transaction, 'order.merchant_order_id')
            ?? data_get($transaction, 'special_reference')
            ?? '');

        return PaymobPayment::query()
            ->where(function ($q) use ($orderId, $reference) {
                $q->when($orderId !== null, fn ($q) => $q->orWhere('paymob_order_id', (string) $orderId))
                    ->when($reference !== '', fn ($q) => $q->orWhere('public_id', $reference));
            })
            ->when($orderId === null && $reference === '', fn ($q) => $q->whereRaw('1 = 0'))
            ->lockForUpdate()
            ->first();
    }

    /** @param array<string, mixed> $transaction */
    private function record(PaymobPayment $payment, array $transaction): PaymentTransaction
    {
        $amount = Money::cents($payment->amount_cents);
        $method = (string) data_get($transaction, 'source_data.sub_type', $payment->method);
        $online = [
            'paymob_order_id' => (string) data_get($transaction, 'order.id', $payment->paymob_order_id),
            'paymob_transaction_id' => (string) ($transaction['id'] ?? ''),
        ];

        $entry = match ($payment->purpose) {
            OnlinePurpose::Sms => $this->paySms($payment, $amount, $method, $online),
            default => $this->payDue($payment, $amount, $method, $online),
        };

        // Online payments have no paper receipt book: the ledger row's own id is the receipt number.
        $entry->update(['inv_no' => $entry->id]);

        return $entry;
    }

    /** @param array{paymob_order_id: string, paymob_transaction_id: string} $online */
    private function payDue(PaymobPayment $payment, Money $amount, string $method, array $online): PaymentTransaction
    {
        $due = Due::where('member_id', $payment->member_id)
            ->where('transaction_type', $payment->purpose->dueType()?->value)
            ->where('item', $payment->reference)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($due) {
            return $this->payDue->handle($due, $amount, now(), $method, Channel::Online, online: $online);
        }

        if ($payment->purpose === OnlinePurpose::Donation) {
            return $this->freeDonation($payment, $amount, $method, $online);
        }

        // The due was settled another way while the member was paying. The money is ours: keep it on the books for review.
        Log::warning('Online payment without a matching due', ['payment_id' => $payment->public_id, 'item' => $payment->reference]);

        return $this->ledger->record($this->entry(TransactionType::Subscription, $payment, $amount, $method, $online));
    }

    /** @param array{paymob_order_id: string, paymob_transaction_id: string} $online */
    private function freeDonation(PaymobPayment $payment, Money $amount, string $method, array $online): PaymentTransaction
    {
        $member = Subscribers::where('member_id', $payment->member_id)->first();
        $entry = $this->ledger->record($this->entry(TransactionType::Donation, $payment, $amount, $method, $online));

        // The inner-donations report reads the donations table.
        if ($member) {
            Donations::create([
                'member_id' => $member->member_id,
                'subscribers_id' => $member->id,
                'invoice_no' => $entry->id,
                'amount' => $amount->toPounds(),
                'donation_type' => 'مادي',
                'donation_category' => $payment->reference,
                'payment_date' => now()->toDateString(),
            ]);
        }

        return $entry;
    }

    /** @param array{paymob_order_id: string, paymob_transaction_id: string} $online */
    private function paySms(PaymobPayment $payment, Money $amount, string $method, array $online): PaymentTransaction
    {
        $entry = $this->ledger->record($this->entry(TransactionType::SmsSubscription, $payment, $amount, $method, $online));

        // The number chosen at checkout; older checkouts fall back to the registered mobile.
        $mobile = $payment->sms_mobile
            ?: (string) Subscribers::where('member_id', $payment->member_id)->value('mobile_no');
        SmsSubscriptionPaid::dispatch($payment->member_id, $mobile, now());

        return $entry;
    }

    /** @param array{paymob_order_id: string, paymob_transaction_id: string} $online */
    private function entry(TransactionType $type, PaymobPayment $payment, Money $amount, string $method, array $online): LedgerEntry
    {
        return new LedgerEntry(
            type: $type,
            direction: Direction::In,
            amount: $amount,
            item: $payment->purpose === OnlinePurpose::Sms ? 'رسائل' : (string) $payment->reference,
            account: null, // held by Paymob until it settles to the bank
            memberId: $payment->member_id,
            paymentMethod: $method,
            channel: Channel::Online,
            paymobOrderId: $online['paymob_order_id'],
            paymobTransactionId: $online['paymob_transaction_id'],
            paymobStatus: 'paid',
        );
    }

    private function label(PaymobPayment $payment): string
    {
        return match ($payment->purpose) {
            OnlinePurpose::Subscription => 'اشتراك '.$payment->reference,
            OnlinePurpose::Donation => 'تبرع '.$payment->reference,
            OnlinePurpose::Sms => 'اشتراك خدمة الرسائل',
        };
    }
}
