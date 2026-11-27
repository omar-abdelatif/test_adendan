<?php

namespace App\Modules\Finance\Application\Actions;

use App\Models\Due;
use App\Models\PaymobPayment;
use App\Models\SMSFEES;
use App\Models\SMSSubscribers;
use App\Models\Subscribers;
use App\Modules\Finance\Domain\Contracts\PaymentGateway;
use App\Modules\Finance\Domain\Enums\OnlinePurpose;
use App\Modules\Finance\Domain\Enums\OnlineStatus;
use App\Modules\Finance\Domain\Exceptions\FinanceException;
use App\Modules\Finance\Domain\Online\CheckoutRequest;
use App\Modules\Finance\Domain\TransferFee;
use App\Modules\Shared\Domain\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Opens a Paymob checkout for the logged-in member (POST /api/payments/checkout).
 *
 * The amount the app sends is only checked, never trusted: it must fit the
 * member's own due (or equal the SMS fee) read from the same tables as the
 * dues endpoints. Nothing is recorded as paid here; only the webhook does that.
 */
class StartPaymobCheckout
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * @return array{payment: PaymobPayment, created: bool}
     *
     * @throws FinanceException when the amount or due is not acceptable (422)
     * @throws RuntimeException when Paymob fails (502)
     */
    public function handle(
        Subscribers $member,
        OnlinePurpose $purpose,
        ?string $reference,
        Money $amount,
        string $method,
        ?string $walletNumber,
        string $idempotencyKey,
    ): array {
        $memberId = (int) $member->member_id;

        if ($existing = $this->find($memberId, $idempotencyKey)) {
            return ['payment' => $this->replay($existing), 'created' => false];
        }

        $reference = $purpose === OnlinePurpose::Sms ? null : trim((string) $reference);
        $this->checkAmount($member, $purpose, $reference, $amount);
        // Computed here, never taken from the app.
        $fee = TransferFee::fromConfig()->for($amount);

        try {
            $payment = PaymobPayment::create([
                'public_id' => (string) Str::ulid(),
                'member_id' => $memberId,
                'purpose' => $purpose,
                'reference' => $reference,
                'amount_cents' => $amount->piasters,
                'fee_cents' => $fee->piasters,
                'currency' => 'EGP',
                'method' => $method,
                'wallet_number_masked' => $walletNumber ? $this->mask($walletNumber) : null,
                'status' => OnlineStatus::Pending,
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addMinutes((int) config('paymob.checkout_ttl_minutes', 60)),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The same tap arrived twice at once; the other request owns the checkout.
            return ['payment' => $this->replay($this->find($memberId, $idempotencyKey)), 'created' => false];
        }

        try {
            $session = $this->gateway->createCheckout(new CheckoutRequest(
                reference: $payment->public_id,
                amount: $amount,
                description: $this->description($purpose, $reference),
                method: $method,
                customerName: (string) $member->name,
                customerPhone: $walletNumber ?: $this->phone($member),
                extras: ['member_id' => $memberId, 'purpose' => $purpose->value, 'reference' => $reference],
                fee: $fee,
            ));
        } catch (RuntimeException $e) {
            $payment->update(['status' => OnlineStatus::Failed, 'failure_reason' => 'paymob_unavailable']);

            throw $e;
        }

        $payment->update([
            'checkout_url' => $session->checkoutUrl,
            'paymob_order_id' => $session->providerOrderId,
        ]);

        return ['payment' => $payment, 'created' => true];
    }

    private function find(int $memberId, string $key): ?PaymobPayment
    {
        return PaymobPayment::where('member_id', $memberId)->where('idempotency_key', $key)->first();
    }

    /** A repeated Idempotency-Key gets the stored checkout back, with no new Paymob call. */
    private function replay(?PaymobPayment $payment): PaymobPayment
    {
        if (! $payment || $payment->status === OnlineStatus::Failed && ! $payment->checkout_url) {
            throw new RuntimeException('The earlier checkout with this key failed.');
        }
        if (! $payment->checkout_url) {
            throw FinanceException::checkoutInProgress();
        }

        return $payment;
    }

    private function checkAmount(Subscribers $member, OnlinePurpose $purpose, ?string $reference, Money $amount): void
    {
        if ($purpose === OnlinePurpose::Sms) {
            if ($this->smsIsActive((int) $member->member_id)) {
                throw FinanceException::smsAlreadyActive();
            }
            if (! $amount->equals(Money::pounds(SMSFEES::query()->orderBy('id')->value('amount')))) {
                throw FinanceException::smsFeeMismatch();
            }

            return;
        }

        $due = Due::where('member_id', $member->member_id)
            ->where('transaction_type', $purpose->dueType()?->value)
            ->where('item', $reference)
            ->orderBy('id')
            ->first();

        if (! $due) {
            // A free donation the member chose in the app has no due: any amount above zero is welcome.
            if ($purpose === OnlinePurpose::Donation) {
                return;
            }

            throw FinanceException::subscriptionAlreadyPaid();
        }

        $remaining = Money::pounds($due->amount_remaining ?? $due->total_amount);
        if ($remaining->isZero()) {
            throw $purpose === OnlinePurpose::Donation
                ? FinanceException::donationAlreadyPaid()
                : FinanceException::subscriptionAlreadyPaid();
        }
        if ($amount->greaterThan($remaining)) {
            throw FinanceException::amountAboveRemaining();
        }
    }

    private function smsIsActive(int $memberId): bool
    {
        $subscription = SMSSubscribers::where('member_id', $memberId)->orderByDesc('id')->first();

        return $subscription
            && $subscription->active_sms
            && (! $subscription->subscription_expiry_date || now()->startOfDay()->lte($subscription->subscription_expiry_date));
    }

    private function description(OnlinePurpose $purpose, ?string $reference): string
    {
        return match ($purpose) {
            OnlinePurpose::Subscription => 'اشتراك '.$reference,
            OnlinePurpose::Donation => 'تبرع '.$reference,
            OnlinePurpose::Sms => 'اشتراك خدمة الرسائل',
        };
    }

    private function phone(Subscribers $member): string
    {
        $digits = preg_replace('/\D/', '', (string) $member->mobile_no) ?? '';

        return $digits === '' ? '' : '0'.ltrim($digits, '0');
    }

    /** 01012345678 -> 010****5678. The full wallet number is never stored. */
    private function mask(string $wallet): string
    {
        return substr($wallet, 0, 3).str_repeat('*', max(0, strlen($wallet) - 7)).substr($wallet, -4);
    }
}
