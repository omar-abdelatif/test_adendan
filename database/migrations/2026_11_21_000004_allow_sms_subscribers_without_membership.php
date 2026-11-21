<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The SMS service also serves non-members (the dashboard counts subscribers
     * with no member_id), but the column was created NOT NULL. Only alters it
     * where it is still NOT NULL.
     */
    public function up(): void
    {
        $isRequired = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'sms_subscribers')
            ->where('COLUMN_NAME', 'member_id')
            ->value('IS_NULLABLE') === 'NO';

        if ($isRequired) {
            DB::statement('ALTER TABLE sms_subscribers MODIFY member_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void {}
};
