<?php

namespace App\Modules\Directory\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tombs;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Illuminate\Http\Request;

class TombsController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:المقابر');
    }

    public function index()
    {
        return view('pages.tombs', ['tombs' => Tombs::orderBy('region')->get()]);
    }

    public function store(Request $request)
    {
        Tombs::create($this->validated($request));

        return $this->success('تم الإضافة بنجاح');
    }

    public function update(Request $request)
    {
        Tombs::findOrFail($request->integer('id'))->update($this->validated($request));

        return $this->success('تم التعديل بنجاح');
    }

    public function destroy(Tombs $tomb)
    {
        $tomb->delete();

        return $this->success('تم الحذف بنجاح');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'tomb_type' => ['required', 'string', 'max:255'],
            'tomb_specialization' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:255'],
            'tomb_guard_name' => ['nullable', 'string', 'max:255'],
            'tomb_guard_number' => ['nullable', 'regex:/^0?1[0-9]{9}$/'],
            'location' => ['nullable', 'string', 'max:255'],
        ], [], ['title' => 'اسم المقبرة', 'region' => 'المنطقة', 'tomb_guard_number' => 'رقم التُربي']);
    }
}
