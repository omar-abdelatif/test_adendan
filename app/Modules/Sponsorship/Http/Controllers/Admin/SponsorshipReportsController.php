<?php

namespace App\Modules\Sponsorship\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SponsorshipCase;
use App\Modules\Shared\Infrastructure\Excel\SheetExport;
use App\Modules\Sponsorship\Application\Queries\CaseViews;
use Maatwebsite\Excel\Facades\Excel;

/** The sponsorship report: cases by benefit and period, by governorate, and the cash cases. */
class SponsorshipReportsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:الكفالة');
    }

    public function index(CaseViews $views)
    {
        return view('pages.sponsorship.reports', $views->report());
    }

    /** Every case with its main data, for Excel. */
    public function export()
    {
        $rows = SponsorshipCase::query()->orderBy('benefit_type')->orderBy('benefit_period')->orderBy('name')->get()
            ->map(fn (SponsorshipCase $c) => [
                $c->name, $c->national_id, $c->phone, $c->age, $c->governorate, $c->address, $c->marital_status?->label(), $c->health_status,
                $c->sons, $c->daughters, $c->monthly_income, $c->pension_income, $c->other_income, $c->totalIncome(),
                $c->benefit_type->label(), $c->benefit_period->label(),
            ])->values()->all();

        return Excel::download(new SheetExport('الحالات', [
            'الاسم', 'الرقم القومي', 'الموبايل', 'السن', 'المحافظة', 'العنوان', 'الحالة الاجتماعية', 'الحالة الصحية',
            'الولاد', 'البنات', 'الدخل الشهري', 'المعاش', 'دخل تاني', 'إجمالي الدخل', 'نوع الاستفادة', 'مدة الاستفادة',
        ], $rows), 'sponsorship-cases-'.now()->format('Y-m-d').'.xlsx');
    }
}
