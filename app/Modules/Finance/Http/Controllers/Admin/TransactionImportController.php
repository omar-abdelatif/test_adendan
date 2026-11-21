<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Infrastructure\Imports\TransactionsImport;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

/** Loads historical payments from a spreadsheet (no balance changes). */
class TransactionImportController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:المشتركين');
    }

    public function __invoke(Request $request)
    {
        $request->validate(['import-transactions' => ['required', 'file', 'mimes:xlsx,xls']]);

        $import = new TransactionsImport;
        Excel::import($import, $request->file('import-transactions'), null, ExcelFormat::XLSX);

        return $this->success("تم استيراد {$import->imported} معاملة بنجاح");
    }
}
