<?php

namespace Database\Seeders;

use App\Models\SMSFEES;
use Database\Seeders\Concerns\GuardsDemoData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fills an empty database with made-up demo data for every module, for a
 * presentation or a test host:
 *
 *   php artisan db:seed --class=PresentationSeeder --force
 *
 * - the admin account from ADMIN_EMAIL / ADMIN_PASSWORD in .env, with every permission;
 * - members, dues, payments, donations, donors, expenses, SMS, the directory,
 *   news and staff (DemoDataSeeder);
 * - cemeteries: tombs, chambers and burials (CemeteryDemoSeeder);
 * - sponsorship: cases, fund donations and transfers (SponsorshipDemoSeeder).
 *
 * On a server it runs only when .env has DEMO_DATA_ALLOWED=true, and it does
 * nothing on a database that already has members.
 */
class PresentationSeeder extends Seeder
{
    use GuardsDemoData;

    public function run(): void
    {
        $this->ensureDemoDataAllowed();

        if (DB::table('subscribers')->exists()) {
            $this->command->warn('This database already has members, so no demo data was added.');

            return;
        }

        $this->command->info('Database: '.DB::connection()->getDatabaseName());

        $this->call(AdminSeeder::class);

        if (! SMSFEES::exists()) {
            $this->call(SmsFeesSeeder::class);
        }

        $this->call([
            DemoDataSeeder::class,
            CemeteryDemoSeeder::class,
            SponsorshipDemoSeeder::class,
        ]);

        $this->command->info('Demo data is ready. Sign in with ADMIN_EMAIL and ADMIN_PASSWORD from .env.');
    }
}
