<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - account: which cash account (safe/bank) a row moved money in or out of.
     *   Old rows stay null and the reports keep reading their legacy values.
     * - proof_img: the receipt image of a transfer or expense, which older code
     *   stored in `item` or `inv_no`.
     * - member_id and inv_no become nullable: expenses and transfers have no
     *   member and no receipt number (older code wrote 0, '' or an image name).
     *   Their existing column type is kept as it is on each server.
     */
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_transactions', 'account')) {
                $table->string('account', 10)->nullable()->after('transaction_cat')->index();
            }
            if (! Schema::hasColumn('payment_transactions', 'proof_img')) {
                $table->string('proof_img')->nullable()->after('account');
            }
        });

        $this->makeNullable('payment_transactions', 'member_id');
        $this->makeNullable('payment_transactions', 'inv_no');
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn(['account', 'proof_img']);
        });
    }

    private function makeNullable(string $table, string $column): void
    {
        $definition = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->first(['COLUMN_TYPE', 'IS_NULLABLE']);

        if ($definition && $definition->IS_NULLABLE === 'NO') {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition->COLUMN_TYPE} NULL");
        }
    }
};
