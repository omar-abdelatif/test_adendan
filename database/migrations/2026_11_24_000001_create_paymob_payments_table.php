<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per checkout the mobile app opens. Only the Paymob webhook turns a
 * row into "paid"; recording the payment itself still goes through the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paymob_payments', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->unsignedBigInteger('member_id');
            $table->enum('purpose', ['subscription', 'donation', 'sms']);
            $table->string('reference')->nullable();
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('EGP');
            $table->enum('method', ['card', 'wallet']);
            $table->string('wallet_number_masked')->nullable();
            $table->enum('status', ['pending', 'paid', 'failed', 'expired'])->default('pending');
            $table->string('idempotency_key', 64);
            $table->string('paymob_order_id')->nullable()->unique();
            $table->string('paymob_transaction_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('receipt_inv_no')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('callback_payload')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'idempotency_key']);
            $table->index(['status', 'expires_at']);
            $table->foreign('member_id')->references('member_id')->on('subscribers')->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paymob_payments');
    }
};
