<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Domain\Permissions;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class PermissionsController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:الصلاحيات');
    }

    public function index()
    {
        return view('pages.permissions.index', ['permissions' => Permission::all()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:permissions,name']], [], ['name' => 'اسم الصلاحية']);
        Permission::create(['name' => $data['name'], 'guard_name' => 'web']);

        return $this->success('تم الإضافة بنجاح');
    }

    public function update(Request $request)
    {
        $permission = Permission::findOrFail($request->integer('id'));
        if (Permissions::isBuiltIn($permission->name)) {
            return $this->failure('هذه الصلاحية يستخدمها النظام ولا يمكن تغيير اسمها.');
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('permissions', 'name')->ignore($permission->id)]]);
        $permission->update(['name' => $data['name']]);

        return $this->success('تم التعديل بنجاح');
    }

    public function destroy(Permission $permission)
    {
        if (Permissions::isBuiltIn($permission->name)) {
            return $this->failure('هذه الصلاحية يستخدمها النظام ولا يمكن حذفها.');
        }
        $permission->delete();

        return $this->success('تم الحذف بنجاح');
    }
}
