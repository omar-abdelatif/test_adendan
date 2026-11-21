<?php

namespace App\Modules\Directory\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Worker;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Illuminate\Http\Request;

/** Craftsmen directory (الحرفيين). */
class WorkersController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:الحرفيين');
    }

    public function index()
    {
        return view('pages.workers', ['workers' => Worker::latest()->get()]);
    }

    public function store(Request $request)
    {
        Worker::create($this->validated($request));

        return $this->success('تم الإضافة بنجاح');
    }

    public function update(Request $request)
    {
        Worker::findOrFail($request->integer('id'))->update($this->validated($request));

        return $this->success('تم التعديل بنجاح');
    }

    public function destroy(Worker $worker)
    {
        $worker->delete();

        return $this->success('تم الحذف بنجاح!');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'regex:/^0?1[0-9]{9}$/'],
            'craft' => ['required', 'string', 'max:50'],
            'other_craft' => ['nullable', 'required_if:craft,أخرى', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
        ], [], ['name' => 'الاسم', 'phone_number' => 'رقم الهاتف', 'craft' => 'الحرفة', 'other_craft' => 'الحرفة الأخرى', 'location' => 'العنوان']);
    }
}
