<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Application\Queries\FinanceReports;
use Illuminate\Http\Request;

class FinanceReportsController extends Controller
{
    public function __construct(private readonly FinanceReports $reports)
    {
        $this->middleware('permission:التقارير');
    }

    public function subscriptions()
    {
        return view('pages.reports.subscriptions', $this->reports->subscriptions());
    }

    public function safe()
    {
        return view('pages.reports.safe', $this->reports->safe());
    }

    public function bank()
    {
        return view('pages.reports.bank', $this->reports->bank());
    }

    public function innerDonations()
    {
        return view('pages.reports.inner_donations', $this->reports->innerDonations());
    }

    public function outerDonations()
    {
        return view('pages.reports.donations', $this->reports->outerDonations());
    }

    public function oldDues(Request $request)
    {
        return view('pages.reports.sub_old_delays', $this->reports->oldDues($request->input('amount'), $request->input('optype')));
    }
}
