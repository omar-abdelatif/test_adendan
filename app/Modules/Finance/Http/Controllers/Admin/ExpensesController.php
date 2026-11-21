<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Miscellaneous;
use App\Modules\Finance\Application\Actions\ManageExpenses;
use App\Modules\Finance\Http\Requests\ExpenseRequest;
use App\Modules\Shared\Domain\Money;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;

/** Petty-cash expenses (نثريات). */
class ExpensesController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:النثريات');
    }

    public function index()
    {
        return view('pages.miscellaneous', ['miscellaneous' => Miscellaneous::latest('id')->get()]);
    }

    public function store(ExpenseRequest $request, ManageExpenses $expenses)
    {
        $expenses->record(
            $request->string('category'),
            $request->input('other_category'),
            Money::pounds($request->input('amount')),
            $request->file('invoice_img'),
        );

        return $this->success('تم الإضافة بنجاح');
    }

    public function update(ExpenseRequest $request, ManageExpenses $expenses)
    {
        $expenses->update(
            Miscellaneous::findOrFail($request->integer('id')),
            $request->string('category'),
            $request->input('other_category'),
            Money::pounds($request->input('amount')),
            $request->file('invoice_img'),
        );

        return $this->success('تم التحديث بنجاح');
    }

    public function destroy(Miscellaneous $expense, ManageExpenses $expenses)
    {
        $expenses->delete($expense);

        return $this->success('تم الحذف بنجاح!');
    }
}
