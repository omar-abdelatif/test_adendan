<?php

namespace App\Modules\Finance\Application\Actions;

use App\Models\Due;
use App\Models\SMSFEES;
use App\Models\Subscribers;
use App\Modules\Finance\Domain\Contracts\PaymentGateway;
use App\Modules\Finance\Domain\Exceptions\FinanceException;
use App\Modules\Finance\Domain\Online\CheckoutRequest;
use App\Modules\Finance\Domain\Online\CheckoutSession;
use App\Modules\Finance\Domain\Online\OrderReference;
use App\Modules\Shared\Domain\Money;
use Illuminate\Support\Str;

/**
 * Opens an online checkout for the logged-in member. The amount always comes
 * from our own records (the due or the SMS fee), never from the app.
 */
class StartOnlineCheckout
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function forDue(Subscribers $member, int $dueId, string $method): CheckoutSession
    {
        $due = Due::where('id', $dueId)->where('member_id', $member->member_id)->first()
            ?? throw FinanceException::dueNotFound();

        $amount = Money::pounds($due->amount_remaining ?? $due->total_amount);
        if ($amount->isZero()) {
            throw FinanceException::nothingToPay();
        }

        $ref = OrderReference::dueRef($due->id, Str::lower(Str::random(6)));

        return $this->start($member, $due->item, (string) $due->transaction_type, $ref, $amount, $method);
    }

    public function forSms(Subscribers $member, string $method): CheckoutSession
    {
        $fee = Money::pounds(SMSFEES::query()->orderBy('id')->value('amount'));
        if ($fee->isZero()) {
            throw FinanceException::nothingToPay();
        }

        return $this->start($member, OrderReference::SMS_ITEM, 'اشتراك', Str::lower(Str::random(10)), $fee, $method);
    }

    private function start(Subscribers $member, string $item, string $purpose, string $ref, Money $amount, string $method): CheckoutSession
    {
        $mobile = '0'.ltrim((string) $member->mobile_no, '0');

        // The reference is '-'-separated, so the parts themselves cannot contain '-'.
        // The due is found by id (in $ref), so the item text is only a label here.
        $reference = new OrderReference(
            memberId: (int) $member->member_id,
            item: str_replace('-', ' ', $item),
            purpose: str_replace('-', ' ', $purpose),
            ref: $ref,
            mobile: $mobile,
        );

        return $this->gateway->createCheckout(new CheckoutRequest(
            reference: $reference,
            amount: $amount,
            description: $item,
            method: $method,
            customerName: (string) $member->name,
            customerPhone: $mobile,
        ));
    }
}
