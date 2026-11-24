<?php

namespace App\Modules\Cemeteries\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CemeteryRegion;
use App\Modules\Cemeteries\Application\Queries\CemeteryViews;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** The cemeteries overview: a card per region with the places free for men and women. */
class CemeteriesController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:المقابر');
    }

    public function index(CemeteryViews $views)
    {
        return view('pages.cemeteries.index', [
            ...$views->overview(),
            'choices' => $views->burialChoices(),
            'allRegions' => CemeteryRegion::orderBy('sort')->orderBy('name')->get(),
        ]);
    }

    public function storeRegion(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('cemetery_regions', 'name')]], [], ['name' => 'اسم المنطقة']);
        $slug = Str::slug($data['name']);
        if ($slug === '' || CemeteryRegion::where('slug', $slug)->exists()) {
            $slug = 'region-'.(CemeteryRegion::max('id') + 1);
        }
        CemeteryRegion::create(['name' => $data['name'], 'slug' => $slug]);

        return $this->success("اتضافت منطقة {$data['name']}");
    }
}
