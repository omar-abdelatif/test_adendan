<?php

namespace App\Modules\Messaging\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SMSSubscribers;
use App\Modules\Messaging\Application\SmsSubscriptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Mobile app: SMS service status and fee, and the device push token. */
class MemberMessagingController extends Controller
{
    public function smsStatus(Request $request): JsonResponse
    {
        $subscription = SMSSubscribers::where('member_id', $request->user()->member_id)->orderByDesc('id')->first();
        if (! $subscription) {
            return response()->json(['success' => false, 'message' => 'لا يوجد اشتراك لهذا العضو'], 404);
        }

        return response()->json(['success' => true, 'sms_status' => [
            'status' => $subscription->active_sms,
            'subscription_date' => $subscription->subscription_start_date,
            'expiry_date' => $subscription->subscription_expiry_date,
        ]]);
    }

    public function smsFee(SmsSubscriptions $subscriptions): JsonResponse
    {
        return response()->json(['success' => true, 'fees' => $subscriptions->fee()->toPounds()]);
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $request->validate(['fcm_token' => ['required', 'string', 'max:500']]);
        $request->user()->forceFill(['fcm_token' => $request->string('fcm_token')])->save();

        return response()->json(['message' => 'تم الحفظ']);
    }
}
