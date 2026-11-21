<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One money movement. Written only through App\Modules\Finance\Application\Ledger.
 *
 * @property int $id
 * @property int|null $member_id
 * @property int $amount
 * @property string $item
 * @property string|null $account
 * @property string|null $proof_img
 * @property int|string|null $inv_no int locally; text on servers where image names were stored in it
 * @property-read string|null $proof_url
 */
class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'item',
        'amount',
        'inv_no',
        'member_id',
        'payment_cat',
        'payment_date',
        'payment_method',
        'transaction_cat',
        'transaction_type',
        'transaction_method',
        'paymob_intention_id',
        'paymob_transaction_id',
        'paymob_status',
        'account',
        'proof_img',
    ];

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscribers::class, 'member_id', 'member_id');
    }

    /**
     * URL of the receipt image, if any. New rows keep "folder/name" in proof_img;
     * older rows kept a bare name in proof_img or inv_no, always under withdraws/.
     */
    protected function proofUrl(): Attribute
    {
        return Attribute::get(function () {
            $path = $this->proof_img;
            if (! $path && $this->inv_no !== null && $this->inv_no !== '' && ! is_numeric($this->inv_no)) {
                $path = $this->inv_no;
            }
            if (! $path) {
                return null;
            }

            return asset('assets/images/'.(str_contains($path, '/') ? $path : 'withdraws/'.$path));
        });
    }
}
