<?php

namespace App\Modules\Members\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CostYears;
use App\Models\Subscribers;
use App\Models\Tombs;
use App\Modules\Finance\Domain\FiscalCalendar;
use App\Modules\Members\Application\Actions\IssueAppPasswords;
use App\Modules\Members\Application\Actions\ManageMember;
use App\Modules\Members\Application\Actions\RegisterMember;
use App\Modules\Members\Http\Requests\MemberRequest;
use App\Modules\Members\Infrastructure\Imports\MembersImport;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class MembersController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:المشتركين');
    }

    /** The members page; the list itself loads through table(). */
    public function index(FiscalCalendar $calendar)
    {
        $year = $calendar->subscriptionYear(now());
        $months = $calendar->remainingMonths(now());
        $cost = (int) CostYears::where('year', $year)->value('cost');

        return view('pages.subscribers.subscribers', [
            'years' => CostYears::orderByDesc('year')->get(),
            'halfDelay' => $months,
            'cost' => $cost,
            'year' => $year,
            'currentSubCost' => $cost / 12 * $months,
            'newMemberId' => (Subscribers::max('member_id') ?? 0) + 1,
        ]);
    }

    /** Server-side DataTables feed for the members list. */
    public function table()
    {
        return DataTables::of(Subscribers::query()->select(['id', 'member_id', 'name', 'img', 'id_img', 'status']))
            ->addColumn('Actions', fn (Subscribers $row) => view('pages.subscribers.partials.table-actions', ['row' => $row])->render())
            ->rawColumns(['Actions'])
            ->make(true);
    }

    public function store(MemberRequest $request, RegisterMember $register)
    {
        return $this->attempt(
            fn () => $register->handle($request->memberAttributes(), $request->file('img'), $request->file('id_img'), $request->input('inv_no')),
            'تم الإضافة بنجاح',
        );
    }

    public function edit(Subscribers $subscriber)
    {
        return view('pages.subscribers.update_subscriber', ['subscriber' => $subscriber, 'tombs' => Tombs::orderBy('title')->get()]);
    }

    public function update(MemberRequest $request, ManageMember $members)
    {
        $members->update(
            Subscribers::findOrFail($request->integer('id')),
            $request->memberAttributes(),
            $request->file('img'),
            $request->file('id_img'),
            deceased: $request->boolean('status'),
        );

        return $this->success('تم التعديل بنجاح');
    }

    public function destroy(Subscribers $subscriber, ManageMember $members)
    {
        return $this->attempt(fn () => $members->delete($subscriber), 'تم الحذف بنجاح');
    }

    public function import(Request $request)
    {
        $request->validate(['import' => ['required', 'file', 'mimes:xlsx,xls']]);

        $import = new MembersImport;
        Excel::import($import, $request->file('import'), null, ExcelFormat::XLSX);

        return $this->success("تم استيراد {$import->imported} عضو بنجاح");
    }

    public function yearCost(string $year): JsonResponse
    {
        return response()->json(['cost' => CostYears::where('year', $year)->value('cost')]);
    }

    /** Gives app passwords (sent by SMS) to members who have none. */
    public function issuePasswords(IssueAppPasswords $passwords): JsonResponse
    {
        $count = $passwords->forMembersWithoutPassword();

        return response()->json([
            'success' => true,
            'message' => "تم انشاء كلمات السر وإرسالها برسالة لعدد {$count} عضو",
            'count' => $count,
        ]);
    }
}
