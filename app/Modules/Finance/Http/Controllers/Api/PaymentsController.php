<?php

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymobPayment;
use App\Models\Subscribers;
use App\Modules\Finance\Application\Actions\StartPaymobCheckout;
use App\Modules\Finance\Domain\Enums\OnlinePurpose;
use App\Modules\Finance\Domain\Enums\OnlineStatus;
use App\Modules\Finance\Domain\Exceptions\FinanceException;
use App\Modules\Finance\Http\Requests\PaymobCheckoutRequest;
use App\Modules\Shared\Domain\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Mobile app payments: open a Paymob checkout, then poll its status.
 * Contract: specs/003-clean-architecture/laravel-payment-requirements.md (app repo).
 */
class PaymentsController extends Controller
{
    /** POST /api/payments/checkout */
    public function checkout(PaymobCheckoutRequest $request, StartPaymobCheckout $checkout): JsonResponse
    {
        $member = $request->user();
        abort_unless($member instanceof Subscribers, 403);

        try {
            ['payment' => $payment, 'created' => $created] = $checkout->handle(
                member: $member,
                purpose: OnlinePurpose::from($request->string('purpose')->value()),
                reference: $request->input('reference'),
                amount: Money::pounds($request->input('amount')),
                method: $request->string('method')->value(),
                walletNumber: $request->input('wallet_number'),
                smsMobile: $request->input('sms_mobile'),
                idempotencyKey: (string) $request->input('idempotency_key'),
            );
        } catch (FinanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException) {
            return response()->json(['message' => 'خدمة الدفع مش متاحة دلوقتي، جرّب تاني بعد شوية'], 502);
        }

        return response()->json([
            'payment_id' => $payment->public_id,
            'checkout_url' => $payment->checkout_url,
            'amount' => $payment->amountInPounds(),
            'fee' => $payment->feeInPounds(),
            'total' => $payment->chargedCents() / 100,
            'expires_at' => $payment->expires_at->utc()->toIso8601ZuluString(),
        ], $created ? 201 : 200);
    }

    /** GET /api/payments/{payment_id}: one indexed query, no Paymob call (the app polls it). */
    public function show(Request $request, string $paymentId): JsonResponse
    {
        $payment = PaymobPayment::where('public_id', $paymentId)
            ->where('member_id', $request->user()?->member_id)
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'العملية غير موجودة'], 404);
        }

        $body = [
            'payment_id' => $payment->public_id,
            'status' => $payment->status->value,
            'amount' => $payment->amountInPounds(),
            'fee' => $payment->feeInPounds(),
            'total' => $payment->chargedCents() / 100,
            'purpose' => $payment->purpose->value,
            'reference' => $payment->reference ?? '',
        ];

        if ($payment->status === OnlineStatus::Paid) {
            $body['receipt'] = [
                'inv_no' => $payment->receipt_inv_no,
                'paid_at' => $payment->paid_at?->utc()->toIso8601ZuluString(),
            ];
        }

        return response()->json($body);
    }
}
