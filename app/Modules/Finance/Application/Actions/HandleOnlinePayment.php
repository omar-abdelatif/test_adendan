<?php

namespace App\Modules\Finance\Application\Actions;

use App\Models\Due;
use App\Models\PaymentTransaction;
use App\Modules\Finance\Application\Ledger;
use App\Modules\Finance\Domain\Contracts\PaymentGateway;
use App\Modules\Finance\Domain\Enums\Channel;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\Enums\TransactionType;
use App\Modules\Finance\Domain\Events\SmsSubscriptionPaid;
use App\Modules\Finance\Domain\LedgerEntry;
use App\Modules\Finance\Domain\Online\OnlinePaymentResult as Result;
use App\Modules\Finance\Domain\Online\OrderReference;
use App\Modules\Shared\Domain\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Applies a payment the provider reports as successful: records it, then pays
 * the member's due or activates their SMS service. Safe to receive twice.
 */
class HandleOnlinePayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly Ledger $ledger,
        private readonly PayDue $payDue,
    ) {}

    /** @param array<string, mixed> $transaction the callback's `obj` */
    public function handle(array $transaction, ?string $signature): Result
    {
        if (! $this->gateway->verifyCallback($transaction, $signature)) {
            return Result::BadSignature;
        }

        $transactionId = (string) ($transaction['id'] ?? '');
        $merchantOrderId = (string) data_get($transaction, 'order.merchant_order_id', '');
        if ($transactionId === '' || $merchantOrderId === '') {
            return Result::Invalid;
        }
        if (($transaction['success'] ?? false) !== true) {
            Log::info('Online payment not successful', ['transaction_id' => $transactionId]);

            return Result::NotSuccessful;
        }
        if (PaymentTransaction::where('paymob_transaction_id', $transactionId)->exists()) {
            return Result::AlreadyRecorded;
        }

        try {
            $reference = OrderReference::parse($merchantOrderId);
        } catch (InvalidArgumentException) {
            Log::warning('Online payment with malformed order id', ['transaction_id' => $transactionId]);

            return Result::Invalid;
        }

        $amount = Money::cents((int) ($transaction['amount_cents'] ?? 0));
        $method = (string) data_get($transaction, 'source_data.sub_type', 'wallet');
        $online = [
            'paymob_order_id' => (string) data_get($transaction, 'order.id'),
            'paymob_transaction_id' => $transactionId,
        ];

        try {
            DB::transaction(function () use ($reference, $amount, $method, $online) {
                $reference->isSms()
                    ? $this->paySms($reference, $amount, $method, $online)
                    : $this->payDueOnline($reference, $amount, $method, $online);
            });
        } catch (UniqueConstraintViolationException) {
            // A parallel retry of the same callback won the race.
            return Result::AlreadyRecorded;
        }

        Log::info('Online payment applied', ['transaction_id' => $transactionId, 'member_id' => $reference->memberId]);

        return Result::Applied;
    }

    private function payDueOnline(OrderReference $reference, Money $amount, string $method, array $online): void
    {
        $due = Due::where('member_id', $reference->memberId)
            ->when(
                $reference->dueId(),
                fn ($q, $id) => $q->whereKey($id),
                fn ($q) => $q->where('item', $reference->item),
            )
            ->first();

        if (! $due) {
            // Money was taken but nothing is owed under that item: keep it on the books for review.
            Log::warning('Online payment without a matching due', ['member_id' => $reference->memberId, 'item' => $reference->item]);
            $this->ledger->record($this->entry(TransactionType::Subscription, $reference, $amount, $method, $online));

            return;
        }

        $this->payDue->handle($due, $amount, now(), $method, Channel::Online, online: $online);
    }

    private function paySms(OrderReference $reference, Money $amount, string $method, array $online): void
    {
        $this->ledger->record($this->entry(TransactionType::SmsSubscription, $reference, $amount, $method, $online));

        SmsSubscriptionPaid::dispatch($reference->memberId, $reference->mobile, now());
    }

    private function entry(TransactionType $type, OrderReference $reference, Money $amount, string $method, array $online): LedgerEntry
    {
        return new LedgerEntry(
            type: $type,
            direction: Direction::In,
            amount: $amount,
            item: $reference->item,
            account: null,
            memberId: $reference->memberId,
            paymentMethod: $method,
            channel: Channel::Online,
            paymobOrderId: $online['paymob_order_id'],
            paymobTransactionId: $online['paymob_transaction_id'],
            paymobStatus: 'paid',
        );
    }
}
