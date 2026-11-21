<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Foreign keys on member_id were ON DELETE CASCADE, so deleting a member
     * silently deleted their whole payment history from the books.
     *
     * - payment_transactions: RESTRICT on delete (history is kept; the app
     *   refuses to delete a member with payments), CASCADE on update so a
     *   corrected membership number follows to the payments.
     * - dues, sms_subscribers: keep CASCADE on delete, add CASCADE on update.
     *
     * Only constraints with these exact names are touched; a server without
     * them is left as it is.
     */
    private const KEYS = [
        'payment_transactions_member_id_foreign' => ['payment_transactions', 'RESTRICT'],
        'dues_member_id_foreign' => ['dues', 'CASCADE'],
        'sms_subscribers_member_id_foreign' => ['sms_subscribers', 'CASCADE'],
    ];

    public function up(): void
    {
        foreach (self::KEYS as $name => [$table, $onDelete]) {
            $this->recreate($name, $table, $onDelete, 'CASCADE');
        }
    }

    public function down(): void
    {
        foreach (self::KEYS as $name => [$table]) {
            $this->recreate($name, $table, 'CASCADE', 'RESTRICT');
        }
    }

    private function recreate(string $name, string $table, string $onDelete, string $onUpdate): void
    {
        $exists = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('CONSTRAINT_NAME', $name)
            ->exists();

        if (! $exists) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
        DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`member_id`) REFERENCES `subscribers` (`member_id`) ON DELETE {$onDelete} ON UPDATE {$onUpdate}");
    }
};
