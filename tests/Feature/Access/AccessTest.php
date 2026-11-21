<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActsAsStaff;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use ActsAsStaff, RefreshDatabase;

    public function test_staff_edit_their_own_profile_without_losing_their_roles(): void
    {
        $user = $this->actingAsStaff('الاخبار');
        $user->assignRole(Role::findOrCreate('editor', 'web'));
        $other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => bcrypt('secret')]);

        // The id in the form is ignored: you can only edit yourself.
        $this->post('/admin/users/update_profile', ['id' => $other->id, 'name' => 'اسمي', 'email' => 'me@example.test'])
            ->assertRedirect('/admin/users/profile');

        $this->assertSame('اسمي', $user->fresh()->name);
        $this->assertSame('Other', $other->fresh()->name);
        $this->assertTrue($user->fresh()->hasRole('editor'));
    }

    public function test_staff_login_locks_after_repeated_failures(): void
    {
        User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => bcrypt('correct-password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'admin@example.test', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'admin@example.test', 'password' => 'correct-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_master_role_passes_every_permission_check(): void
    {
        $user = $this->actingAsStaff();
        $user->assignRole(Role::findOrCreate('master', 'web'));
        Permission::findOrCreate('الرسائل', 'web');

        $this->get('/admin/sms/sms')->assertOk();
    }

    public function test_built_in_permissions_and_master_role_cannot_be_deleted(): void
    {
        $this->actingAsStaff('الصلاحيات', 'الادوار');
        $builtIn = Permission::findOrCreate('التقارير', 'web');
        $master = Role::findOrCreate('master', 'web');

        $this->delete("/admin/permissions/delete/{$builtIn->id}")->assertSessionHas('alert-type', 'error');
        $this->delete("/admin/roles/delete/{$master->id}")->assertSessionHas('alert-type', 'error');

        $this->assertNotNull($builtIn->fresh());
        $this->assertNotNull($master->fresh());
    }

    public function test_staff_cannot_delete_their_own_account(): void
    {
        $user = $this->actingAsStaff('المستخدمين');

        $this->delete("/admin/users/user/delete/{$user->id}")->assertSessionHas('alert-type', 'error');

        $this->assertNotNull($user->fresh());
    }

    public function test_creating_staff_requires_a_strong_password_and_a_role(): void
    {
        $this->actingAsStaff('المستخدمين');
        Role::findOrCreate('cashier', 'web');

        $this->post('/admin/users/user/store', ['name' => 'X', 'email' => 'x@example.test', 'password' => '123', 'role' => 'cashier'])
            ->assertSessionHasErrors('password');
        $this->post('/admin/users/user/store', ['name' => 'X', 'email' => 'x@example.test', 'password' => 'long-enough-1', 'role' => 'cashier'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'x@example.test')->first()->hasRole('cashier'));
    }

    public function test_access_pages_render(): void
    {
        $this->actingAsStaff('المستخدمين', 'الادوار', 'الصلاحيات', 'سجل النشاطات');

        foreach (['dashboard/main', 'users/profile', 'users/users/all', 'roles/roles', 'permissions/permissions', 'activiy/activity'] as $page) {
            $this->get('/admin/'.$page)->assertOk();
        }
    }
}
