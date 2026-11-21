<?php

namespace App\Modules\Directory\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wedding;
use App\Modules\Directory\Infrastructure\WeddingsImport;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class WeddingsController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:الافراح');
    }

    public function index()
    {
        return view('pages.weddings', ['weddings' => Wedding::orderByDesc('date')->get()]);
    }

    public function store(Request $request)
    {
        Wedding::create($this->validated($request, creating: true));

        return $this->success('تم الإضافة بنجاح');
    }

    public function update(Request $request)
    {
        Wedding::findOrFail($request->integer('id'))->update($this->validated($request, creating: false));

        return $this->success('تم التحديث بنجاح');
    }

    public function destroy(Wedding $wedding)
    {
        $wedding->delete();

        return $this->success('تم الحذف بنجاح');
    }

    public function import(Request $request)
    {
        $request->validate(['import' => ['required', 'file', 'mimes:xlsx,xls']]);

        $import = new WeddingsImport;
        Excel::import($import, $request->file('import'), null, ExcelFormat::XLSX);

        return $this->success("تم رفع {$import->imported} فرح بنجاح");
    }

    /** Times arrive as "HH:MM" from the form and are stored as "g:i A" (the existing format). */
    private function validated(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'day' => [$required, 'string', 'max:50'],
            'date' => [$required, 'date'],
            'groom_name' => [$required, 'string', 'max:255'],
            'pride_father_name' => [$required, 'string', 'max:255'],
            'address' => [$required, 'string', 'max:255'],
            'from_time' => [$creating ? 'required' : 'nullable', 'date_format:H:i'],
            'to_time' => [$creating ? 'required' : 'nullable', 'date_format:H:i'],
        ], [], ['day' => 'اليوم', 'date' => 'التاريخ', 'groom_name' => 'اسم العريس', 'pride_father_name' => 'اسم والد العروسة', 'address' => 'العنوان', 'from_time' => 'من', 'to_time' => 'إلى']);

        foreach (['from_time', 'to_time'] as $field) {
            if (empty($data[$field])) {
                unset($data[$field]);
            } else {
                $data[$field] = Carbon::createFromFormat('H:i', $data[$field])->format('g:i A');
            }
        }

        return $data;
    }
}
