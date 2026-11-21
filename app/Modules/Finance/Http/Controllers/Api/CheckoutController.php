<?php

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscribers;
use App\Modules\Finance\Application\Actions\StartOnlineCheckout;
use App\Modules\Finance\Domain\Exceptions\FinanceException;
use App\Modules\Finance\Http\Requests\CheckoutRequest;
use Closure;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Mobile app: opens a Paymob checkout. The app shows checkout_url; the due or
 * SMS service is updated only when Paymob confirms the payment (callback).
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly StartOnlineCheckout $checkout) {}

    /** POST /api/payments/checkout {due_id, payment_method} */
    public function due(CheckoutRequest $request): JsonResponse
    {
        return $this->respond(fn (Subscribers $member) => $this->checkout->forDue(
            $member, $request->integer('due_id'), $request->string('payment_method'),
        ), $request->user());
    }

    /** POST /api/sms/pay_subscription and /api/sms/renew_subscription {payment_method} */
    public function sms(CheckoutRequest $request): JsonResponse
    {
        return $this->respond(fn (Subscribers $member) => $this->checkout->forSms(
            $member, $request->string('payment_method'),
        ), $request->user());
    }

    private function respond(Closure $start, mixed $member): JsonResponse
    {
        abort_unless($member instanceof Subscribers, 403);

        try {
            $session = $start($member);
        } catch (FinanceException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }

        return response()->json(['success' => true, 'checkout' => $session]);
    }
}
