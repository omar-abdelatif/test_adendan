<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every permission the app checks for, a "master" role that holds
 * all of them, and an admin user with that role.
 *
 * Credentials come from .env (ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD).
 * Run: php artisan db:seed --class=AdminSeeder
 */
class AdminSeeder extends Seeder
{
    /** Permission names used in routes (permission:...) and views (@can). */
    private const PERMISSIONS = [
        'لوحة التحكم',
        'الاخبار',
        'المشتركين',
        'مشترك جديد',
        'الاشتراكات',
        'الإشتراك السنوي',
        'المديونيات',
        'مديونية الاشتراك',
        'مديونية التبرعات',
        'التبرعات',
        'المتبرعين',
        'التبرعات الخارجية',
        'النثريات',
        'مجلس الادارة',
        'اللجان',
        'الحرفيين',
        'المقابر',
        'الافراح',
        'الرسائل',
        'التقارير',
        'المستخدمين',
        'الادوار',
        'الصلاحيات',
        'سجل النشاطات',
        'sms-delete-subscriber',
    ];

    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command->error('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env first.');
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
        $master->syncPermissions(Permission::where('guard_name', 'web')->get());

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => Hash::make($password),
                'role' => 'admin',
            ]
        );
        $user->syncRoles([$master]);

        $this->command->info("Admin ready: {$email}");
    }
}
