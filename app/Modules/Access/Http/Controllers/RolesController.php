<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Domain\Permissions;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:الادوار');
    }

    public function index()
    {
        $rolePermissions = DB::table('role_has_permissions')->get(['role_id', 'permission_id'])
            ->groupBy('role_id')
            ->map(fn ($rows) => $rows->pluck('permission_id')->all())
            ->all();

        return view('pages.roles.index', ['roles' => Role::all(), 'permissions' => Permission::all(), 'rolePermissions' => $rolePermissions]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        Role::create(['name' => $data['name']])->syncPermissions($data['permissions']);

        return $this->success('تم الإضافة بنجاح');
    }

    public function update(Request $request)
    {
        $role = Role::findOrFail($request->integer('id'));
        $data = $this->validated($request, $role);

        // The master role keeps its name and every permission.
        if ($role->name === Permissions::SUPER_ROLE) {
            $role->syncPermissions(Permission::all());

            return $this->success('دور master يحتفظ بكل الصلاحيات دائمًا');
        }

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions']);

        return $this->success('تم التعديل بنجاح');
    }

    public function destroy(Role $role)
    {
        if ($role->name === Permissions::SUPER_ROLE) {
            return $this->failure('لا يمكن حذف دور master.');
        }
        $role->syncPermissions([]);
        $role->delete();

        return $this->success('تم الحذف بنجاح');
    }

    /** @return array{name: string, permissions: list<string>} */
    private function validated(Request $request, ?Role $role): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ], [], ['name' => 'اسم الدور', 'permissions' => 'الصلاحيات']) + ['permissions' => []];
    }
}
