<?php

namespace App\Http\Controllers;

use App\Models\CostYears;
use App\Models\Donations;
use App\Models\Due;
use App\Models\OcrData;
use App\Models\OuterDonations;
use App\Models\PaymentTransaction;
use App\Models\SearchedData;
use App\Models\Subscribers;
use App\Models\TotalBank;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:التقارير');
    }

    

    

    // ! Job Reports
    public function jobs(Request $request)
    {
        [$keyword, $jobs, $message] = $this->jobFilter($request);

        return view('pages.reports.jobs', compact('keyword', 'jobs', 'message'));
    }

    public function jobFilter(Request $request)
    {
        $keyword = $request->input('search');
        $jobs = collect();
        if ($keyword == '') {
            $message = 'برجاء إدخال إسم وظيفة.';
        } else {
            $jobs = Subscribers::where('job', 'like', "%$keyword%")->get();
            if ($jobs->isEmpty()) {
                $message = 'لا توجد نتائج بحث عن "'.$keyword.'".';
            }
        }

        return [$keyword, $jobs, $message ?? null];
    }

    // ! Ages Reports
    public function ages(Request $request)
    {
        [$ageKey, $ages, $message] = $this->ageFilter($request);

        return view('pages.reports.age', compact('ages', 'ageKey', 'message'));
    }

    public function ageFilter(Request $request)
    {
        $ageKey = $request->input('birthdate');
        $ages = collect();
        if ($ageKey == '') {
            $message = 'برجاء إدخال سنة ميلاد صحيح';
        } else {
            $year = date('Y', strtotime($ageKey));
            $ages = Subscribers::whereYear('birthdate', $year)->get();
            if ($ages->isEmpty()) {
                $message = 'لا توجد نتائج بحث عن "'.$ageKey.'".';
            }
        }

        return [$ageKey, $ages, $message ?? null];
    }

    // ! Address Reports
    public function locations(Request $request)
    {
        [$locationKey, $locations, $message] = $this->locationFilter($request);

        return view('pages.reports.address', compact('locations', 'locationKey', 'message'));
    }

    public function locationFilter(Request $request)
    {
        $locationKey = $request->input('address');
        $locations = collect();
        if ($locationKey == '') {
            $message = 'برجاء إدخال إسم المنطقة.';
        } else {
            $locations = Subscribers::where('address', 'like', "%$locationKey%")->get();
            if ($locations->isEmpty()) {
                $message = 'لا توجد نتائج بحث عن "'.$locationKey.'".';
            }
        }

        return [$locationKey, $locations, $message ?? null];
    }

    

    

    

    

    // ! Associates ( إنتساب )
    public function associates()
    {
        $associates = Subscribers::where('membership_type', 'إنتساب')->get();

        return view('pages.reports.associates', compact('associates'));
    }

    

    // ! Incomplete Data Reports
    public function incomplete()
    {
        $incompleteSSN = Subscribers::where('ssn', 0)->get();
        $incompleteSSNCount = $incompleteSSN->count();
        $incompleteMobile = Subscribers::where('mobile_no', 0)->get();
        $incompleteMobileCount = $incompleteMobile->count();
        $incompleteAddress = Subscribers::where('address', null)->orWhere('address', '')->get();
        $incompleteAddressCount = $incompleteAddress->count();

        return view('pages.reports.incomplete', compact('incompleteSSN', 'incompleteSSNCount', 'incompleteMobile', 'incompleteMobileCount', 'incompleteAddress', 'incompleteAddressCount'));
    }

    // ! Subscribers Data
    public function searchedData()
    {
        $data = SearchedData::all();
        $ocr = OcrData::all();

        return view('pages.reports.subscribers_data_update', compact('data', 'ocr'));
    }

    public function approveOcrData(int $ocr_id)
    {
        $ocrData = OcrData::findOrFail($ocr_id);
        $member = Subscribers::where('ssn', $ocrData->nid)->first();

        if ($member) {
            $data = [
                'ssn' => $ocrData->nid,
                'birthdate' => $ocrData->birth_date,
                'address' => $ocrData->address,
                'mobile_no' => $ocrData->mobile,
            ];
            $update = $member->update($data);
            if ($update) {
                $ocrData->delete();
                $notificationSuccess = [
                    'message' => 'تم الحديث بنجاح',
                    'alert-type' => 'success',
                ];

                return redirect()->back()->with($notificationSuccess);
            }
        }
        $subscriber = Subscribers::where('mobile_no', $ocrData->mobile)->first();
        if ($subscriber) {
            $subscriber->update([
                'name' => $ocrData->name,
                'ssn' => $ocrData->nid,
                'address' => $ocrData->address,
                'birth_date' => $ocrData->birth_date,
                'mobile_no' => $ocrData->mobile,
            ]);
            $notification = [
                'message' => 'خطأ اثناء التحديث',
                'alert-type' => 'error',
            ];

            return redirect()->back()->with($notification);
        }
        $notification = [
            'message' => 'خطأ اثناء التحديث',
            'alert-type' => 'error',
        ];

        return redirect()->back()->with($notification);
    }

    
}
