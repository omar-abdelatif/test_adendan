<?php

namespace App\Models;

use App\Modules\Finance\Domain\Enums\OnlinePurpose;
use App\Modules\Finance\Domain\Enums\OnlineStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A checkout the mobile app opened. Created by StartPaymobCheckout; only
 * RecordPaymobPayment (the webhook) marks it paid. The app only ever sees
 * public_id, never the numeric id.
 *
 * @property int $id
 * @property string $public_id
 * @property int $member_id
 * @property OnlinePurpose $purpose
 * @property string|null $reference
 * @property int $amount_cents
 * @property int $fee_cents
 * @property string $currency
 * @property string $method
 * @property string|null $wallet_number_masked
 * @property OnlineStatus $status
 * @property string $idempotency_key
 * @property string|null $paymob_order_id
 * @property string|null $paymob_transaction_id
 * @property string|null $checkout_url
 * @property Carbon $expires_at
 * @property Carbon|null $paid_at
 * @property string|null $receipt_inv_no
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $callback_payload
 */
class PaymobPayment extends Model
{
    protected $fillable = [
        'public_id', 'member_id', 'purpose', 'reference', 'amount_cents', 'fee_cents', 'currency', 'method',
        'wallet_number_masked', 'status', 'idempotency_key', 'paymob_order_id', 'paymob_transaction_id',
        'checkout_url', 'expires_at', 'paid_at', 'receipt_inv_no', 'failure_reason', 'callback_payload',
    ];

    protected $casts = [
        'purpose' => OnlinePurpose::class,
        'status' => OnlineStatus::class,
        'amount_cents' => 'integer',
        'fee_cents' => 'integer',
        'member_id' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'callback_payload' => 'array',
    ];

    /** @return BelongsTo<Subscribers, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Subscribers::class, 'member_id', 'member_id');
    }

    /** What is credited to the member: the amount, without the transfer fee. */
    public function amountInPounds(): float
    {
        return $this->amount_cents / 100;
    }

    public function feeInPounds(): float
    {
        return $this->fee_cents / 100;
    }

    /** What Paymob charges: the amount plus the transfer fee. */
    public function chargedCents(): int
    {
        return $this->amount_cents + $this->fee_cents;
    }
}
