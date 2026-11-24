<?php

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SMSSubscribers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/sms/pay_subscription and /api/sms/renew_subscription.
 *
 * Older app versions call these after their own payment page says "success".
 * That request can be sent by hand, so it activates nothing any more: only the
 * Paymob webhook does. It answers 200 with the member's current SMS status so
 * those apps keep working.
 */
class LegacySmsPaymentController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $subscription = SMSSubscribers::where('member_id', $request->user()?->member_id)->orderByDesc('id')->first();

        return response()->json([
            'success' => true,
            'message' => 'تم استلام طلبك، وهيتفعّل الاشتراك أول ما الدفع يتأكد',
            'sms_status' => $subscription ? [
                'status' => $subscription->active_sms,
                'subscription_date' => $subscription->subscription_start_date,
                'expiry_date' => $subscription->subscription_expiry_date,
            ] : null,
        ]);
    }
}
