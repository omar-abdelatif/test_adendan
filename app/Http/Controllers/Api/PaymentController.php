<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\EgyptLinxSmsService;
use App\Services\PaymobService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller {
    public function __construct(private PaymobService $paymobService, protected EgyptLinxSmsService $egyptLinkService) {}
    public function callback(Request $request) {
        $type = $request->input('type');
        $obj = $request->input('obj');
        if ($type !== 'TRANSACTION' || !is_array($obj)) {
            Log::warning('Paymob callback with invalid structure', ['type' => $type]);
            return response()->json(['message' => 'بيانات غير صحيحة'], 400);
        }
        if (!$this->paymobService->verifyHmac($obj, $request->query('hmac'))) {
            return response()->json(['message' => 'بيانات غير صحيحة (فشل التشفير)'], 403);
        }
        $merchantOrderId = $obj['order']['merchant_order_id'] ?? null;
        $orderId = $obj['order']['id'] ?? null;
        $transactionId = $obj['id'] ?? null;
        $amountCents = $obj['amount_cents'] ?? 0;
        $success = $obj['success'] ?? false;
        if (!$merchantOrderId || !$transactionId) {
            Log::warning('Paymob callback missing ids', ['transaction_id' => $transactionId]);
            return response()->json(['message' => 'بيانات ناقصة'], 400);
        }
        if (!$success) {
            Log::info('Paymob payment not successful', ['transaction_id' => $transactionId]);
            return response()->json(['message' => 'فشل الدفع'], 400);
        }
        // Paymob retries callbacks: a transaction we already recorded must not be applied twice.
        if (PaymentTransaction::where('paymob_transaction_id', (string) $transactionId)->exists()) {
            return response()->json(['message' => 'تم تسجيل العملية من قبل']);
        }
        // merchant_order_id = memberId-item-transactionMethod-<ref>-mobile
        $userData = explode('-', $merchantOrderId);
        if (count($userData) < 5) {
            Log::warning('Invalid merchant_order_id format', ['transaction_id' => $transactionId]);
            return response()->json(['message' => 'بيانات غير صحيحة'], 400);
        }
        $memberId = (int) $userData[0];
        $item = trim($userData[1]);
        $amount = $amountCents / 100;
        $subscriptionDate = now();
        $transactionMethod = $userData[2];
        $mobileNumber = trim($userData[4]);
        $paymentMethod = $obj['source_data']['sub_type'] ?? 'wallet';
        Log::info('Processing Paymob transaction', [
            'transaction_id' => $transactionId,
            'member_id' => $memberId,
            'item' => $item,
            'amount' => $amount,
        ]);
        try {
            $this->applyPayment($memberId, $amount, $item, $paymentMethod, (string) $transactionId, $orderId, $transactionMethod, $subscriptionDate, $mobileNumber);
        } catch (UniqueConstraintViolationException) {
            // A parallel retry of the same callback won the race; the payment is already recorded.
            return response()->json(['message' => 'تم تسجيل العملية من قبل']);
        }
        return response()->json(['message' => 'تم الدفع بنجاح']);
    }
    private function applyPayment(int $memberId, $amount, string $item, string $paymentMethod, string $transactionId, $orderId, string $transactionMethod, $subscriptionDate, string $mobileNumber): void {
        DB::transaction(function () use ($memberId, $amount, $item, $paymentMethod, $transactionId, $orderId, $transactionMethod, $subscriptionDate, $mobileNumber) {
            $paymentCat = $item === 'رسائل' ? 'كلي' : $this->paymobService->calculatePaymentCat($amount, $memberId, $item);
            if ($item === 'رسائل') {
                $transaction = $this->createTransaction($memberId, $amount, $subscriptionDate, $paymentMethod, $paymentCat, $item, $orderId, $transactionMethod);
                $transaction->update([
                    'paymob_transaction_id' => $transactionId,
                    'paymob_status' => 'paid',
                ]);
                $this->egyptLinkService->storeOrUpdateSmsSubscriber($memberId, $mobileNumber, $subscriptionDate);
            } else {
                $transaction = $this->createTransaction($memberId, $amount, $subscriptionDate, $paymentMethod, $paymentCat, $item, $orderId, $transactionMethod);
                $transaction->update([
                    'paymob_transaction_id' => $transactionId,
                    'paymob_status' => 'paid',
                ]);
                $this->deductDue($transaction);
            }
            Log::info('Paymob transaction completed', ['id' => $transaction->id]);
        });
    }
    private function deductDue(PaymentTransaction $transaction): void {
        $this->paymobService->deductDue($transaction);
    }
    private function createTransaction(int $memberId, int $amount, $paymentDate, string $paymentMethod, string $paymentCat, string $item, ?string $paymobIntentionId = null, string $transactionType): PaymentTransaction {
        return paymentTransaction(memberId: $memberId, amount: $amount, paymentDate: $paymentDate, paymentMethod: $paymentMethod, paymentCategory: $paymentCat, transactionType: $transactionType, transactionCategory: 'ايداع', item: $item, inv: 0, paymobIntentionId: $paymobIntentionId, paymobStatus: 'pending', transactionMethod: 'دفع اونلاين',);
    }
}
