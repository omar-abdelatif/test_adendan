<?php

namespace App\Services;

use App\Models\Due;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymobService
{
    /**
     * Paymob signs the "processed" callback by concatenating these fields of `obj`
     * in this exact order. Booleans are signed as the words "true"/"false".
     */
    private const HMAC_FIELDS = [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction',
        'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded',
        'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending',
        'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
    ];

    public function verifyHmac(array $data, ?string $receivedHmac): bool
    {
        $secret = config('services.paymob.hmac_secret');
        if (! $secret || ! $receivedHmac) {
            return false;
        }
        $hash = hash_hmac('sha512', $this->hmacString($data), $secret);
        $valid = hash_equals($hash, strtolower($receivedHmac));
        if (! $valid) {
            Log::warning('Paymob HMAC verification failed', ['transaction_id' => $data['id'] ?? null]);
        }

        return $valid;
    }

    public function hmacString(array $data): string
    {
        return implode('', array_map(function (string $key) use ($data) {
            $value = data_get($data, $key, '');
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            return (string) ($value ?? '');
        }, self::HMAC_FIELDS));
    }

    public function deductDue(PaymentTransaction $transaction): void
    {
        $due = Due::where('member_id', $transaction->member_id)->where('item', $transaction->item)->first();
        if (! $due) {
            throw new \Exception('لا يوجد مستحق لهذا العضو');
        }
        DB::transaction(function () use ($due, $transaction) {
            $currentRemaining = $due->amount_remaining ?? $due->total_amount;
            $newAmountRemaining = max(0, $currentRemaining - $transaction->amount);
            if ($newAmountRemaining === 0) {
                $due->delete();
            } else {
                $due->update([
                    'amount_paid' => $due->amount_paid + $transaction->amount,
                    'amount_remaining' => $newAmountRemaining,
                ]);
            }
        });
    }

    public function calculatePaymentCat(int $amount, int $memberId, string $item): string
    {
        $due = Due::where('member_id', $memberId)->where('item', $item)->first();
        if (! $due) {
            throw new \Exception('لا يوجد مستحق لهذا العضو');
        }
        $remaining = $due->amount_remaining ?? $due->total_amount;
        $paymentCat = $amount >= $remaining ? 'كلي' : 'جزئي';

        return $paymentCat;
    }
}
