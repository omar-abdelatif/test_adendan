<?php

namespace Tests\Concerns;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

trait ActsAsStaff
{
    /** Logs in a dashboard user holding exactly these permissions. */
    protected function actingAsStaff(string ...$permissions): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::create(['name' => 'Staff', 'email' => uniqid('staff').'@example.test', 'password' => bcrypt('secret')]);
        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }

        $this->actingAs($user);

        return $user;
    }
}
