<?php

namespace App\Modules\Content\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssociationCommittes;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Illuminate\Http\Request;

/** The association's committees (اللجان). */
class CommitteesController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:اللجان');
    }

    public function index()
    {
        return view('pages.association_committes', ['associations' => AssociationCommittes::all()]);
    }

    public function store(Request $request)
    {
        AssociationCommittes::create($this->validated($request));

        return $this->success('تم الإضافة بنجاح');
    }

    public function update(Request $request)
    {
        AssociationCommittes::findOrFail($request->integer('id'))->update($this->validated($request));

        return $this->success('تم التعديل بنجاح');
    }

    public function destroy(AssociationCommittes $committee)
    {
        $committee->delete();

        return $this->success('تم الحذف بنجاح');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'boss' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'tasks' => ['required', 'string', 'max:255'],
        ], [], ['name' => 'اسم اللجنة', 'boss' => 'رئيس اللجنة', 'description' => 'الوصف', 'tasks' => 'المهام']);
    }
}
