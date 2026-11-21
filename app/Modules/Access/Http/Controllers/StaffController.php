<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Dashboard accounts: create, edit, remove, and their roles and permissions. */
class StaffController extends Controller
{
    use RespondsWithAlerts;

    public function __construct()
    {
        $this->middleware('permission:المستخدمين');
    }

    public function index()
    {
        $usersPermissions = DB::table('model_has_permissions')
            ->where('model_type', User::class)
            ->get(['model_id', 'permission_id'])
            ->groupBy('model_id')
            ->map(fn ($rows) => $rows->pluck('permission_id')->all())
            ->all();

        return view('pages.users.index', [
            'users' => User::with('roles')->get(),
            'roles' => Role::all(),
            'permissions' => Permission::all(),
            'usersPermissions' => $usersPermissions,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);

        DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
            $user->syncRoles([$data['role']]);
            $user->syncPermissions($data['permission']);
        });

        return $this->success('تم إضافة المستخدم بنجاح');
    }

    public function update(Request $request)
    {
        $user = User::findOrFail($request->integer('id'));
        $data = $this->validated($request, $user);

        DB::transaction(function () use ($user, $data) {
            $user->fill(['name' => $data['name'], 'email' => $data['email']]);
            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }
            $user->save();
            $user->syncRoles([$data['role']]);
            $user->syncPermissions($data['permission']);
        });

        return $this->success('تم التعديل بنجاح');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return $this->failure('لا يمكنك حذف حسابك الشخصي.');
        }

        DB::transaction(function () use ($user) {
            $user->syncRoles([]);
            $user->syncPermissions([]);
            $user->delete();
        });

        return $this->success('تم حذف المستخدم بنجاح');
    }

    /** @return array{name: string, email: string, password: ?string, role: string, permission: list<string>} */
    private function validated(Request $request, ?User $user): array
    {
        // The create form sends one permission, the edit form a list.
        $request->merge(['permission' => array_values(array_filter((array) $request->input('permission', [])))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
            'role' => ['required', 'exists:roles,name'],
            'permission' => ['array'],
            'permission.*' => ['exists:permissions,name'],
        ], [], ['name' => 'الاسم', 'email' => 'البريد الإلكتروني', 'password' => 'كلمة المرور', 'role' => 'الدور']);

        return $data + ['password' => null, 'permission' => []];
    }
}
