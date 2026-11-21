<?php

namespace App\Modules\Members\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Members\Application\Queries\MemberReports;
use Illuminate\Http\Request;

class MemberReportsController extends Controller
{
    public function __construct(private readonly MemberReports $reports)
    {
        $this->middleware('permission:التقارير');
    }

    public function jobs(Request $request)
    {
        return view('pages.reports.jobs', $this->reports->byJob($request->input('search')));
    }

    public function ages(Request $request)
    {
        return view('pages.reports.age', $this->reports->byBirthYear($request->input('birthdate')));
    }

    public function locations(Request $request)
    {
        return view('pages.reports.address', $this->reports->byAddress($request->input('address')));
    }

    public function associates()
    {
        return view('pages.reports.associates', $this->reports->associates());
    }

    public function incomplete()
    {
        return view('pages.reports.incomplete', $this->reports->incomplete());
    }

    public function pendingChanges()
    {
        return view('pages.reports.subscribers_data_update', $this->reports->pendingProfileChanges());
    }
}
