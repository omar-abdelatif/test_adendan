<?php

namespace App\Modules\Members\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserUpdateStaging;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Mobile app: a member asks staff to correct their data (staff approve it in the dashboard). */
class ProfileController extends Controller
{
    public function requestUpdate(Request $request): JsonResponse
    {
        $member = $request->user();

        if (UserUpdateStaging::where('member_id', $member->member_id)->exists()) {
            return response()->json(['message' => 'تم تقديم طلب التعديل بالفعل، برجاء الانتظار حتى يتم معالجة الطلب.'], 409);
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'mobile_no' => ['nullable', 'regex:/^01[0-9]{9}$/'],
            'ssn' => ['nullable', 'digits:14'],
            'birth_date' => ['nullable', 'date'],
            'job_title' => ['nullable', 'string', 'max:255'],
        ]);

        $request = UserUpdateStaging::create([
            'member_id' => $member->member_id,
            'name' => $data['name'] ?? $member->name,
            'address' => $data['address'] ?? $member->address,
            'mobile_no' => $data['mobile_no'] ?? $member->mobile_no,
            'ssn' => $data['ssn'] ?? $member->ssn,
            'birth_date' => isset($data['birth_date']) ? Carbon::parse($data['birth_date'])->toDateString() : $member->birthdate,
            'job_title' => $data['job_title'] ?? $member->job,
        ]);

        return response()->json(['message' => 'تم ارسال طلب التحديث، الطلب قيد المعالجة.', 'data' => $request]);
    }

    /**
     * Whether the logged-in member has a request waiting. The {member_id} in the
     * URL is kept for app compatibility but ignored: members only see their own.
     */
    public function hasPendingUpdate(Request $request): JsonResponse
    {
        return response()->json([
            'success' => UserUpdateStaging::where('member_id', $request->user()->member_id)->exists(),
        ]);
    }
}
