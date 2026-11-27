<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The transfer fee charged on top of amount_cents. Paymob charges
 * amount_cents + fee_cents; only amount_cents is credited to the member.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paymob_payments', function (Blueprint $table) {
            $table->unsignedInteger('fee_cents')->default(0)->after('amount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('paymob_payments', function (Blueprint $table) {
            $table->dropColumn('fee_cents');
        });
    }
};
