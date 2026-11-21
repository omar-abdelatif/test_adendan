<?php

namespace App\Modules\Members\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\SearchedData;
use App\Models\Subscribers;
use App\Modules\Members\Application\Queries\PublicMemberLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Public website: members check their dues and send missing data. */
class MemberLookupController extends Controller
{
    public function result(Request $request, PublicMemberLookup $lookup)
    {
        $ssn = trim((string) $request->input('ssn'));
        if ($ssn === '') {
            return redirect()->route('site.search')->with('empty_message', 'برجاء إدخال بيانات صحيحة');
        }

        $result = $lookup->byNationalId($ssn);
        if ($result === null) {
            return redirect()->route('site.search')->with([
                'empty_message' => 'الرقم القومي غير صحيح',
                'empty_message2' => 'برجاء ادخال البيانات لتحديثها من خلال الرابط التالي,',
                'link_title' => 'تحديث البيانات',
            ]);
        }

        return response()->json(['status' => true, 'data' => $result]);
    }

    public function details(string $slug)
    {
        return view('frontend.pages.search_details.search-details', [
            'member' => Subscribers::where('slug', $slug)->select(['id', 'name', 'slug', 'member_id'])->firstOrFail(),
        ]);
    }

    /** A visitor sends data to complete their record; staff review it in the dashboard. */
    public function storeRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'ssn' => ['nullable', 'regex:/^(2|3)[0-9]{13}$/', 'unique:searched_data,ssn'],
            'address' => ['nullable', 'string', 'max:255'],
            'mobile_no' => ['nullable', 'numeric', 'digits:11', 'unique:searched_data,mobile_no'],
            'birthdate' => ['nullable', 'date'],
            'member_id' => ['nullable', 'integer', 'unique:searched_data,member_id'],
        ]);

        SearchedData::create($data);

        return response()->json(['status' => true, 'message' => 'سيقوم المختص بالتحديث']);
    }
}
