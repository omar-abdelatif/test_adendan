<?php

namespace App\Modules\Members\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserUpdateStaging;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Mobile app: a member asks staff to correct their data (staff approve it in the dashboard). */
class ProfileController extends Controller
{
    /** Request field => the member's column it changes. */
    private const COLUMNS = [
        'name' => 'name',
        'address' => 'address',
        'mobile_no' => 'mobile_no',
        'ssn' => 'ssn',
        'birth_date' => 'birthdate',
        'job_title' => 'job',
    ];

    /**
     * Only the fields that differ from the member's record are kept; the rest
     * stay null, so staff see exactly what the member changed.
     */
    public function requestUpdate(Request $request): JsonResponse
    {
        $member = $request->user();

        if (UserUpdateStaging::where('member_id', $member->member_id)->exists()) {
            return response()->json(['message' => 'تم تقديم طلب التعديل بالفعل، برجاء الانتظار حتى يتم معالجة الطلب.'], 409);
        }

        // Older app versions send `job` and `birthdate`.
        $input = $request->all();
        $input['job_title'] ??= $input['job'] ?? null;
        $input['birth_date'] ??= $input['birthdate'] ?? null;

        $validator = Validator::make($input, [
            'name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'mobile_no' => ['nullable', 'regex:/^01[0125][0-9]{8}$/'],
            'ssn' => ['nullable', 'digits:14'],
            'birth_date' => ['nullable', 'date'],
            'job_title' => ['nullable', 'string', 'max:255'],
        ], [
            'mobile_no.regex' => 'رقم الهاتف غير صحيح، اكتب 11 رقم يبدأ بـ 010 أو 011 أو 012 أو 015',
            'ssn.digits' => 'الرقم القومي لازم يكون 14 رقم',
            'birth_date.date' => 'تاريخ الميلاد غير صحيح',
            '*.max' => 'النص طويل جدًا',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $changes = [];
        foreach (self::COLUMNS as $field => $column) {
            $value = $this->clean($field, $validator->validated()[$field] ?? null);
            if ($value !== null && $value !== $this->clean($field, $member->{$column})) {
                $changes[$field] = $value;
            }
        }
        if ($changes === []) {
            return response()->json(['message' => 'لم تقم بتعديل أي بيانات.'], 422);
        }

        $staged = UserUpdateStaging::create(['member_id' => $member->member_id, ...$changes]);

        return response()->json(['message' => 'تم ارسال طلب التحديث، الطلب قيد المعالجة.', 'data' => $staged]);
    }

    /** Comparable form of a value: trimmed, phones with their leading 0, dates as Y-m-d. */
    private function clean(string $field, mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return match ($field) {
            'mobile_no' => '0'.ltrim(preg_replace('/\D/', '', $value) ?? '', '0'),
            'birth_date' => rescue(fn () => Carbon::parse($value)->toDateString(), $value, false),
            default => $value,
        };
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
