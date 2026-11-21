<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The code reads and writes columns that the migration history never created
     * (the live database was changed by hand). Every step here checks the current
     * schema first, so it fixes a fresh install and is a no-op where the column
     * already exists.
     */
    public function up(): void
    {
        $this->renameIfNeeded('news_comments', from: 'comment', to: 'content');
        $this->renameIfNeeded('reminders', from: 'send_at', to: 'sent_at');

        $this->addIfMissing('news', 'slug', fn (Blueprint $t) => $t->string('slug')->nullable()->after('title'));
        $this->addIfMissing('news', 'shortlink', fn (Blueprint $t) => $t->string('shortlink')->nullable()->after('slug'));
        $this->addIfMissing('outer_donations', 'donation_type', fn (Blueprint $t) => $t->string('donation_type')->nullable());
        $this->addIfMissing('outer_donations', 'payment_method', fn (Blueprint $t) => $t->string('payment_method')->nullable());
        $this->addIfMissing('outer_donations', 'duration', fn (Blueprint $t) => $t->string('duration')->nullable());
    }

    /**
     * Nothing to undo safely: on a database that already had these columns,
     * dropping them would delete real data.
     */
    public function down(): void {}

    private function renameIfNeeded(string $table, string $from, string $to): void
    {
        if (Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
            Schema::table($table, fn (Blueprint $t) => $t->renameColumn($from, $to));
        }
    }

    private function addIfMissing(string $table, string $column, Closure $definition): void
    {
        if (! Schema::hasColumn($table, $column)) {
            Schema::table($table, $definition);
        }
    }
};
