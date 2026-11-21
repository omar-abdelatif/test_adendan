<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Update requests carry only the fields a member wants to change, but these
     * columns were NOT NULL: a website form without a name, or an app request
     * from a member with no address on file, failed with a database error.
     * Each column keeps its current type on every server.
     */
    private const COLUMNS = [
        'searched_data' => ['name', 'address', 'birthdate', 'mobile_no', 'ssn'],
        'user_update_stagings' => ['name', 'address', 'birth_date', 'job_title', 'mobile_no', 'ssn'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $definition = DB::table('information_schema.COLUMNS')
                    ->where('TABLE_SCHEMA', DB::getDatabaseName())
                    ->where('TABLE_NAME', $table)
                    ->where('COLUMN_NAME', $column)
                    ->first(['COLUMN_TYPE', 'IS_NULLABLE']);

                if ($definition && $definition->IS_NULLABLE === 'NO') {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition->COLUMN_TYPE} NULL");
                }
            }
        }
    }

    public function down(): void {}
};
