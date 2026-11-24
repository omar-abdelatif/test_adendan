<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sponsorship (الكفالة) merged in from the old adndan project:
 * - sponsorship_cases replaces adndan's table_case. Phones and national IDs are
 *   text, so the leading zero stays; the total income is worked out, not stored.
 * - sponsorship_case_files holds the case documents on the private disk.
 * - fund_donations holds donations to the sponsorship and new-tombs funds. The
 *   donor is a row of the existing `donators` table (one person, whatever they
 *   give to), and every donation has its fund and the ledger row it wrote.
 */
return new class extends Migration
{
    /** Permissions the new screens check; created here so production has them on deploy. */
    private const PERMISSIONS = ['الكفالة', 'خزنة الكفالة', 'خزنة المقابر الجديدة'];

    public function up(): void
    {
        Schema::create('sponsorship_cases', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('national_id', 14)->nullable()->unique();
            $table->string('phone', 15)->nullable();
            $table->string('phone_alt', 15)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('address')->nullable();
            $table->string('governorate', 60)->nullable()->index();
            $table->string('marital_status', 20)->nullable();
            $table->string('health_status')->nullable();
            $table->unsignedTinyInteger('sons')->default(0);
            $table->unsignedTinyInteger('daughters')->default(0);
            $table->unsignedInteger('monthly_income')->default(0);
            $table->unsignedInteger('pension_income')->default(0);
            $table->unsignedInteger('other_income')->default(0);
            $table->string('benefit_type', 20)->index();
            $table->string('benefit_period', 20)->index();
            $table->text('notes')->nullable();
            $table->string('legacy_source', 40)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->index(['legacy_source', 'legacy_id']);
        });

        Schema::create('sponsorship_case_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('sponsorship_cases')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('fund_donations', function (Blueprint $table) {
            $table->id();
            $table->string('fund', 20);
            // donators.id; no foreign key because the live table's id type was hand-edited.
            $table->unsignedBigInteger('donator_id')->index();
            $table->unsignedInteger('amount');
            $table->string('account', 10)->default('safe');
            $table->string('invoice_no', 30)->nullable();
            $table->string('category', 40)->nullable();
            $table->string('category_other')->nullable();
            $table->json('months')->nullable();
            $table->date('donated_on');
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('legacy_source', 40)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->index(['fund', 'donated_on']);
            $table->index(['fund', 'invoice_no']);
            $table->index(['legacy_source', 'legacy_id']);
        });

        if (Schema::hasTable('permissions')) {
            foreach (self::PERMISSIONS as $name) {
                Permission::findOrCreate($name, 'web');
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_donations');
        Schema::dropIfExists('sponsorship_case_files');
        Schema::dropIfExists('sponsorship_cases');
        // The permissions stay: roles may already hold them.
    }
};
