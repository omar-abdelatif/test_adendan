<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('banks')) {
            return;
        }
        Schema::table('banks', function (Blueprint $table) {
            $table->string('proof_img')->after('amount')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('banks')) {
            return;
        }
        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn('proof_img');
        });
    }
};
