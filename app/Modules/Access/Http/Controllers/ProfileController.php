<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use App\Modules\Shared\Infrastructure\ImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Every staff member edits their own profile here. It used to need the
 * "users" permission, took the user id from the form, and wiped the editor's
 * roles on save.
 */
class ProfileController extends Controller
{
    use RespondsWithAlerts;

    public function show(Request $request)
    {
        return view('pages.users.profile', ['user' => $request->user()]);
    }

    public function update(Request $request, ImageStorage $images)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::min(8)],
            'avatar' => Controller::IMAGE_RULE,
        ], [], ['name' => 'الاسم', 'email' => 'البريد الإلكتروني', 'password' => 'كلمة المرور', 'avatar' => 'الصورة']);

        if ($request->hasFile('avatar')) {
            $user->avatar = $images->replace($user->avatar, $request->file('avatar'), 'users');
        }
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->fill(['name' => $data['name'], 'email' => $data['email']])->save();

        return redirect()->route('user.profile')->with(['message' => 'تم تحديث البيانات بنجاح', 'alert-type' => 'success']);
    }
}
