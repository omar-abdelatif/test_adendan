<?php

namespace App\Modules\Members\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OcrData;
use App\Models\SearchedData;
use App\Models\UserUpdateStaging;
use App\Modules\Members\Application\Actions\ApplyProfileChanges;
use App\Modules\Members\Domain\MemberException;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Staff approve or reject member data sent from the website, the OCR engine and the app. */
class ProfileChangesController extends Controller
{
    use RespondsWithAlerts;

    public function __construct(private readonly ApplyProfileChanges $changes)
    {
        $this->middleware('permission:التقارير')->except(['appRequests', 'approveApp', 'declineApp']);
        $this->middleware('permission:المستخدمين')->only(['appRequests', 'approveApp', 'declineApp']);
    }

    // Website requests (searched_data)

    public function approveWebsite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer'],
            'ssn' => ['nullable', 'digits:14'],
            'birthdate' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'mobile_no' => ['nullable', 'regex:/^01[0-9]{9}$/'],
        ]);

        try {
            $id = $this->changes->fromWebsite((int) $data['member_id'], collect($data)->except('member_id')->all());
        } catch (MemberException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 400);
        }

        return response()->json(['status' => true, 'message' => 'تم تحديث البيانات بنجاح', 'id' => $id]);
    }

    public function deleteWebsite(SearchedData $request)
    {
        $request->delete();

        return $this->success('تم الحذف بنجاح');
    }

    // OCR scans (ocr_data)

    public function approveOcr(OcrData $scan)
    {
        return $this->attempt(fn () => $this->changes->fromOcr($scan), 'تم التحديث بنجاح');
    }

    public function deleteOcr(OcrData $scan)
    {
        $scan->delete();

        return $this->success('تم الحذف بنجاح');
    }

    // Mobile app requests (user_update_stagings)

    public function appRequests()
    {
        return view('pages.api.users.users_update_request', [
            'requests' => UserUpdateStaging::select('id', 'member_id', 'name', 'address', 'mobile_no', 'ssn', 'job_title', 'birth_date', 'created_at')->latest('id')->get(),
        ]);
    }

    public function approveApp(Request $request): JsonResponse
    {
        // The database keeps phones as numbers, so a request can carry one
        // without its leading 0 (1012345678); that is still a valid phone.
        $mobile = preg_replace('/\D/', '', (string) $request->input('mobile_no')) ?? '';
        $request->merge(['mobile_no' => $mobile === '' ? null : '0'.ltrim($mobile, '0')]);

        $reviewed = $request->validate([
            'member_id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'mobile_no' => ['nullable', 'regex:/^01[0-9]{9}$/'],
            'ssn' => ['nullable', 'digits:14'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date'],
        ], [
            'mobile_no.regex' => 'رقم الهاتف في الطلب غير صحيح',
            'ssn.digits' => 'الرقم القومي في الطلب لازم يكون 14 رقم',
            'birthdate.date' => 'تاريخ الميلاد في الطلب غير صحيح',
        ]);

        try {
            $this->changes->fromApp((int) $reviewed['member_id'], $reviewed);
        } catch (MemberException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }

        return response()->json(['success' => true, 'message' => 'تم قبول طلب تحديث']);
    }

    public function declineApp(UserUpdateStaging $staging): JsonResponse
    {
        $staging->delete();

        return response()->json(['success' => true, 'title' => 'تم رفض طلب تحديث', 'message' => 'تم رفض طلب تحديث']);
    }
}
