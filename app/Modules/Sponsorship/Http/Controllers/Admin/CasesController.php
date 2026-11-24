<?php

namespace App\Modules\Sponsorship\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SponsorshipCase;
use App\Models\SponsorshipCaseFile;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use App\Modules\Sponsorship\Application\Actions\ManageCases;
use App\Modules\Sponsorship\Application\Queries\CaseViews;
use App\Modules\Sponsorship\Domain\Enums\CaseDocument;
use App\Modules\Sponsorship\Http\Requests\CaseRequest;
use App\Modules\Sponsorship\Infrastructure\CaseFiles;
use App\Modules\Sponsorship\Infrastructure\Imports\CasesImport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

/** The cases (cards with filters), the case file drawer, papers, and the Excel import. */
class CasesController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:الكفالة');
    }

    public function index(Request $request, CaseViews $views)
    {
        $filters = $request->only(['q', 'type', 'period', 'gov', 'sort']);

        return view('pages.sponsorship.cases', [
            'cases' => $views->cards($filters),
            'counts' => $views->counts(),
            'governorates' => $views->governorates(),
            'filters' => $filters,
            'sorts' => CaseViews::SORTS,
        ]);
    }

    /** The case file, loaded into the drawer. */
    public function show(SponsorshipCase $case)
    {
        return view('pages.sponsorship.partials.case-file', ['case' => $case->load('files')]);
    }

    public function store(CaseRequest $request, ManageCases $cases)
    {
        $case = $cases->create($request->caseData(), $request->uploads());

        return back()->with(['message' => "اتضافت حالة {$case->name}", 'alert-type' => 'success', 'open_case' => $case->id]);
    }

    public function update(CaseRequest $request, SponsorshipCase $case, ManageCases $cases)
    {
        $cases->update($case, $request->caseData(), $request->uploads());

        return back()->with(['message' => "اتعدلت بيانات {$case->name}", 'alert-type' => 'success', 'open_case' => $case->id]);
    }

    public function destroy(SponsorshipCase $case, ManageCases $cases)
    {
        $cases->delete($case);

        return $this->success("اتحذفت حالة {$case->name} وكل مستنداتها");
    }

    public function addFiles(Request $request, SponsorshipCase $case, ManageCases $cases)
    {
        $request->validate([
            'kind' => ['required', Rule::enum(CaseDocument::class)],
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ], [], ['kind' => 'نوع المستند', 'files' => 'الملفات', 'files.*' => 'الملف']);

        $kind = CaseDocument::from($request->input('kind'));
        $cases->addFiles($case, array_map(fn ($file) => [$kind, $file], $request->file('files')));

        return back()->with(['message' => 'اتضافت المستندات لملف '.$case->name, 'alert-type' => 'success', 'open_case' => $case->id]);
    }

    public function deleteFile(SponsorshipCaseFile $file, ManageCases $cases)
    {
        $cases->deleteFile($file);

        return back()->with(['message' => 'اتحذف المستند', 'alert-type' => 'success', 'open_case' => $file->case_id]);
    }

    /** A paper from a case file, opened only from the dashboard. */
    public function file(SponsorshipCaseFile $file, CaseFiles $files)
    {
        return $files->open($file);
    }

    public function import(Request $request, ManageCases $cases)
    {
        $request->validate(['sheet' => ['required', 'file', 'mimes:xlsx,xls']], [], ['sheet' => 'ملف الإكسل']);

        $import = new CasesImport($cases);
        Excel::import($import, $request->file('sheet'), null, ExcelFormat::XLSX);

        $message = "اتضافت {$import->imported} حالة";
        if ($import->skipped) {
            $message .= ' · اتساب '.count($import->skipped).': '.implode('، ', array_slice($import->skipped, 0, 3));
        }

        return back()->with(['message' => $message, 'alert-type' => $import->skipped ? 'warning' : 'success']);
    }
}
