<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Due;
use App\Modules\Finance\Application\Actions\IssueDues;
use App\Modules\Finance\Application\Actions\PayDue;
use App\Modules\Finance\Domain\Enums\DueType;
use App\Modules\Finance\Domain\Enums\PaymentCategory;
use App\Modules\Finance\Domain\Exceptions\FinanceException;
use App\Modules\Finance\Http\Requests\IssueDuesRequest;
use App\Modules\Finance\Http\Requests\PayDueRequest;
use App\Modules\Finance\Infrastructure\Imports\DuesImport;
use App\Modules\Shared\Domain\Money;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class DuesController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:المديونيات');
    }

    /** Office payment of a subscription or donation due. */
    public function pay(PayDueRequest $request, PayDue $payDue)
    {
        $due = Due::where('id', $request->integer('id'))
            ->where('member_id', $request->integer('member_id'))
            ->where('item', $request->string('item'))
            ->first();

        if (! $due) {
            return $this->failure(FinanceException::dueNotFound()->getMessage());
        }

        $transaction = $payDue->handle(
            due: $due,
            amount: Money::pounds($request->input('amount_paid')),
            date: $request->filled('payment_date') ? CarbonImmutable::parse($request->input('payment_date')) : CarbonImmutable::now(),
            paymentMethod: $request->string('payment_method'),
            invoiceNo: $request->input('inv_no'),
        );

        return $this->success($transaction->payment_cat === PaymentCategory::Full->value ? 'تم دفع كل المبلغ' : 'تم دفع جزء من المبلغ');
    }

    /** Charges every active member the subscription of a year. */
    public function issueSubscriptionDues(IssueDuesRequest $request, IssueDues $issueDues)
    {
        return $this->attempt(
            fn () => $issueDues->handle(DueType::Subscription, $request->item(), $request->amount()),
            'تم أضافة السنة المالية للمشتركين بنجاح',
        );
    }

    public function import(Request $request)
    {
        $request->validate(['import-dues' => ['required', 'file', 'mimes:xlsx,xls']]);

        $import = new DuesImport;
        Excel::import($import, $request->file('import-dues'), null, ExcelFormat::XLSX);

        return $this->success("تم استيراد {$import->imported} مستحق بنجاح");
    }
}
