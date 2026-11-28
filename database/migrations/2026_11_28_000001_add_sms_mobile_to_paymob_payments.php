<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The number an SMS subscription is paid for. The member may choose a number
 * other than their registered mobile; the webhook activates this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paymob_payments', function (Blueprint $table) {
            $table->string('sms_mobile', 11)->nullable()->after('wallet_number_masked');
        });
    }

    public function down(): void
    {
        Schema::table('paymob_payments', function (Blueprint $table) {
            $table->dropColumn('sms_mobile');
        });
    }
};
