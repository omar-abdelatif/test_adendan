<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stops a Paymob callback from being recorded twice.
     *
     * If duplicates already exist, the migration stops and lists them instead of
     * deleting anything: these are money records and need a human review first.
     */
    public function up(): void
    {
        $duplicates = DB::table('payment_transactions')
            ->select('paymob_transaction_id', DB::raw('COUNT(*) as copies'))
            ->whereNotNull('paymob_transaction_id')
            ->groupBy('paymob_transaction_id')
            ->having('copies', '>', 1)
            ->pluck('copies', 'paymob_transaction_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Duplicate paymob_transaction_id values must be reviewed before this migration can run: '
                . $duplicates->map(fn ($copies, $id) => "{$id} ({$copies}x)")->implode(', ')
            );
        }

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->unique('paymob_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropUnique(['paymob_transaction_id']);
        });
    }
};
