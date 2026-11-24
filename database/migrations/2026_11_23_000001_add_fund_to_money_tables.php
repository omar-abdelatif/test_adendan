<?php

use App\Modules\Finance\Domain\Enums\Fund;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money now belongs to a fund (the association, sponsorship, or the new tombs).
     *
     * - payment_transactions.fund: every existing row is the association's.
     * - total_safes / total_banks.fund: one balance row per fund. The rows the
     *   app already uses become the association's. On a database that is already
     *   in use the other funds start at 0 here, so a first payment never has to
     *   create its balance row under a race. An empty table (a fresh install)
     *   stays empty and Treasury creates the rows when they are first needed.
     */
    public function up(): void
    {
        foreach (['payment_transactions', 'total_safes', 'total_banks'] as $table) {
            if (! Schema::hasColumn($table, 'fund')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->string('fund', 20)->default(Fund::General->value)->after('id')->index();
                });
            }
        }

        foreach (['total_safes', 'total_banks'] as $table) {
            if (! DB::table($table)->exists()) {
                continue;
            }
            foreach (Fund::cases() as $fund) {
                if ($fund === Fund::General || DB::table($table)->where('fund', $fund->value)->exists()) {
                    continue;
                }
                DB::table($table)->insert(['fund' => $fund->value, 'amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        foreach (['total_safes', 'total_banks'] as $table) {
            DB::table($table)->where('fund', '!=', Fund::General->value)->delete();
        }
        foreach (['payment_transactions', 'total_safes', 'total_banks'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['fund']);
                $blueprint->dropColumn('fund');
            });
        }
    }
};
