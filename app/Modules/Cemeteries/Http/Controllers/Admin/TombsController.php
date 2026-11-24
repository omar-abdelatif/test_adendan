<?php

namespace App\Modules\Cemeteries\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CemeteryChamber;
use App\Models\CemeteryRegion;
use App\Models\CemeteryTomb;
use App\Modules\Cemeteries\Application\Actions\ManageBurials;
use App\Modules\Cemeteries\Application\Actions\ManageTombs;
use App\Modules\Cemeteries\Application\Queries\CemeteryViews;
use App\Modules\Cemeteries\Domain\CemeteryException;
use App\Modules\Cemeteries\Http\Requests\TombRequest;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;

/** One tomb with its chambers; adding, changing and removing tombs; clearing a chamber. */
class TombsController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:المقابر');
    }

    public function show(CemeteryTomb $tomb, CemeteryViews $views)
    {
        return view('pages.cemeteries.tomb', [
            ...$views->tomb($tomb),
            'choices' => $views->burialChoices(),
            'allRegions' => CemeteryRegion::orderBy('sort')->orderBy('name')->get(),
        ]);
    }

    public function store(TombRequest $request, ManageTombs $tombs)
    {
        $tomb = $tombs->create($request->tombAttributes(), $request->integer('chambers'));

        return redirect()->route('cemeteries.tombs.show', $tomb)->with(['message' => "اتضافت {$tomb->name}", 'alert-type' => 'success']);
    }

    public function update(TombRequest $request, CemeteryTomb $tomb, ManageTombs $tombs)
    {
        return $this->attempt(fn () => $tombs->update($tomb, $request->tombAttributes(), $request->integer('chambers')), 'اتعدلت بيانات المقبرة');
    }

    public function destroy(CemeteryTomb $tomb, ManageTombs $tombs)
    {
        try {
            $tombs->delete($tomb);
        } catch (CemeteryException $e) {
            return $this->failure($e->getMessage());
        }

        // Its page is gone, so go back to the overview.
        return redirect()->route('cemeteries.index')->with(['message' => "اتحذفت {$tomb->name}", 'alert-type' => 'success']);
    }

    public function clearChamber(CemeteryChamber $chamber, ManageBurials $burials)
    {
        try {
            $count = $burials->clearChamber($chamber);
        } catch (CemeteryException $e) {
            return $this->failure($e->getMessage());
        }

        return $this->success("اتطهرت {$chamber->label()}، و{$count} دفنة اتنقلت للمتوفيين السابقين");
    }
}
