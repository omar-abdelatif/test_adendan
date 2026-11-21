<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EgyptLinxSmsService;
use App\Services\SMSService;
use Illuminate\Http\Request;

class SMSController extends Controller
{
    public function __construct(protected EgyptLinxSmsService $egyptLinkService, protected SMSService $smsService) {}

    public function getSmsStatus(Request $request)
    {
        $user = $request->user();
        $memberId = $user->member_id;
        $smsStatus = $this->smsService->getSmsStatus($memberId);
        if ($smsStatus === false) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد اشتراك لهذا العضو',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'sms_status' => $smsStatus,
        ]);
    }

    public function getSmsFees()
    {
        $fees = $this->egyptLinkService->smsFees();

        return response()->json([
            'success' => true,
            'fees' => $fees,
        ]);
    }
}
