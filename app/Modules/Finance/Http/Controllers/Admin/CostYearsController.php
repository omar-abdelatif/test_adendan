<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CostYears;
use App\Modules\Finance\Http\Requests\CostYearRequest;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;

/** The yearly subscription price. */
class CostYearsController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:الإشتراك السنوي');
    }

    public function index()
    {
        return view('pages.cost_years', ['years' => CostYears::orderByDesc('year')->get()]);
    }

    public function store(CostYearRequest $request)
    {
        CostYears::create($request->only(['year', 'cost']));

        return $this->success('تم الإضافة بنجاح');
    }

    public function update(CostYearRequest $request)
    {
        CostYears::findOrFail($request->integer('id'))->update($request->only(['year', 'cost']));

        return $this->success('تم التعديل بنجاح');
    }

    public function destroy(CostYears $year)
    {
        $year->delete();

        return $this->success('تم الحذف بنجاح');
    }
}
