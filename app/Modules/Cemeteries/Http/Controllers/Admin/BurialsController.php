<?php

namespace App\Modules\Cemeteries\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Burial;
use App\Models\BurialFile;
use App\Models\CemeteryRegion;
use App\Modules\Cemeteries\Application\Actions\ManageBurials;
use App\Modules\Cemeteries\Application\Queries\CemeteryViews;
use App\Modules\Cemeteries\Domain\CemeteryException;
use App\Modules\Cemeteries\Http\Requests\BurialRequest;
use App\Modules\Cemeteries\Infrastructure\BurialFiles;
use App\Modules\Cemeteries\Infrastructure\Imports\PastBurialsImport;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

/** The burials list (in chambers, past, village) and adding, changing and removing burials. */
class BurialsController extends Controller
{
    use RespondsWithAlerts;

    public const TABS = ['chambers' => 'في العيون', 'past' => 'المتوفيين السابقين', 'village' => 'القرية'];

    public function __construct()
    {
        $this->middleware('permission:المقابر');
    }

    public function index(Request $request, CemeteryViews $views)
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'chambers';
        $scope = fn () => match ($tab) {
            'past' => Burial::query()->past(),
            'village' => Burial::query()->inVillage(),
            default => Burial::query()->inChambers(),
        };

        return view('pages.cemeteries.burials', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'counts' => [
                'chambers' => Burial::query()->inChambers()->count(),
                'past' => Burial::query()->past()->count(),
                'village' => Burial::query()->inVillage()->count(),
            ],
            'burials' => $scope()->with(['region', 'tomb', 'chamber.tomb', 'member:id,member_id', 'files'])->latest('burial_date')->latest('id')->get(),
            'choices' => $views->burialChoices(),
            'allRegions' => CemeteryRegion::orderBy('sort')->orderBy('name')->get(),
        ]);
    }

    public function store(BurialRequest $request, ManageBurials $burials)
    {
        return $this->attempt(
            fn () => $request->boolean('past') ? $burials->recordPast($request->burialData()) : $burials->record($request->burialData(), $request->uploads()),
            'اتسجلت الدفنة',
        );
    }

    public function update(BurialRequest $request, Burial $burial, ManageBurials $burials)
    {
        return $this->attempt(fn () => $burials->update($burial, $request->burialData(), $request->uploads()), 'اتعدلت بيانات الدفنة');
    }

    public function destroy(Burial $burial, ManageBurials $burials)
    {
        $burials->delete($burial);

        return $this->success("اتحذفت دفنة {$burial->name}");
    }

    public function import(Request $request, ManageBurials $burials)
    {
        $request->validate(['sheet' => ['required', 'file', 'mimes:xlsx,xls']], [], ['sheet' => 'ملف الإكسل']);

        $import = new PastBurialsImport($burials);
        try {
            Excel::import($import, $request->file('sheet'), null, ExcelFormat::XLSX);
        } catch (CemeteryException $e) {
            return $this->failure($e->getMessage());
        }

        $message = "اتضاف {$import->imported} متوفي للسجل";
        if ($import->skipped) {
            $message .= ' · اتساب '.count($import->skipped).': '.implode('، ', array_slice($import->skipped, 0, 3));
        }

        return back()->with(['message' => $message, 'alert-type' => $import->skipped ? 'warning' : 'success']);
    }

    /** A photo or certificate, opened only from the dashboard. */
    public function file(BurialFile $file, BurialFiles $files)
    {
        return $files->open($file);
    }
}
