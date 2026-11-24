<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * The cemeteries module: regions → tombs → chambers (عيون or لحود) → burials.
     *
     * The directory's tombs table is copied in (one table for every tomb, as agreed),
     * then kept as directory_tombs_archive instead of being dropped. The old table
     * stored "who" in tomb_type (رجال/سيدات/مختلط) and "what" in
     * tomb_specialization (لحد/عيون); the new columns are serves and kind.
     */
    public function up(): void
    {
        Schema::create('cemetery_regions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            // The village keeps a burial log without tombs or chambers.
            $table->boolean('is_village')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('cemetery_tombs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('cemetery_regions')->restrictOnDelete();
            $table->string('name')->unique();
            $table->string('kind', 10);    // TombKind: lahd | eyes
            $table->string('serves', 10);  // TombServes: men | women | mixed
            $table->unsignedInteger('annual_cost')->nullable();
            $table->string('location', 500)->nullable();
            $table->string('guard_name')->nullable();
            $table->string('guard_phone', 20)->nullable();
            $table->unsignedBigInteger('directory_id')->nullable()->unique(); // its row in the old directory table
            $table->timestamps();
        });

        Schema::create('cemetery_chambers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tomb_id')->constrained('cemetery_tombs')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->unsignedTinyInteger('capacity');
            $table->timestamp('last_cleared_at')->nullable();
            $table->timestamps();
            $table->unique(['tomb_id', 'number']);
        });

        Schema::create('burials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('cemetery_regions')->restrictOnDelete();
            $table->foreignId('tomb_id')->nullable()->constrained('cemetery_tombs')->restrictOnDelete();
            $table->foreignId('chamber_id')->nullable()->constrained('cemetery_chambers')->restrictOnDelete();
            $table->unsignedBigInteger('subscriber_id')->nullable()->index(); // optional link to the member
            $table->string('name');
            $table->string('gender', 10)->nullable(); // Gender: male | female (unknown in some old records)
            $table->unsignedSmallInteger('age')->nullable();
            $table->unsignedTinyInteger('places')->default(1);
            $table->string('death_place')->nullable();
            $table->date('death_date')->nullable();
            $table->date('burial_date')->nullable();
            $table->unsignedInteger('burial_cost')->nullable();
            $table->string('washer')->nullable();
            $table->string('carrier')->nullable();
            $table->text('notes')->nullable();
            // Set when the chamber is cleared, or for past burials brought in from records.
            $table->timestamp('cleared_at')->nullable()->index();
            $table->string('legacy_source', 30)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->index(['chamber_id', 'cleared_at']);
            $table->unique(['legacy_source', 'legacy_id']);
        });

        Schema::create('burial_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('burial_id')->constrained('burials')->cascadeOnDelete();
            $table->string('kind', 20); // photo | certificate
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->timestamps();
        });

        $this->copyDirectoryTombs();
    }

    public function down(): void
    {
        if (Schema::hasTable('directory_tombs_archive') && ! Schema::hasTable('tombs')) {
            Schema::rename('directory_tombs_archive', 'tombs');
        }
        Schema::dropIfExists('burial_files');
        Schema::dropIfExists('burials');
        Schema::dropIfExists('cemetery_chambers');
        Schema::dropIfExists('cemetery_tombs');
        Schema::dropIfExists('cemetery_regions');
    }

    private function copyDirectoryTombs(): void
    {
        if (! Schema::hasTable('tombs')) {
            return;
        }

        $now = now();
        $regionId = function (string $name) use ($now): int {
            $name = trim(preg_replace('/^(\d+)\s*/u', '$1 ', $name)); // "15مايو" → "15 مايو"
            $existing = DB::table('cemetery_regions')->where('name', $name)->value('id');
            if ($existing) {
                return $existing;
            }

            $slug = Str::slug($name);
            if ($slug === '' || DB::table('cemetery_regions')->where('slug', $slug)->exists()) {
                $slug = 'region-'.(DB::table('cemetery_regions')->count() + 1);
            }

            return DB::table('cemetery_regions')->insertGetId([
                'name' => $name,
                'slug' => $slug,
                'is_village' => $name === 'القرية',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        };

        // The village is always there, with or without tombs in the directory.
        $regionId('القرية');

        foreach (DB::table('tombs')->orderBy('id')->get() as $old) {
            DB::table('cemetery_tombs')->insert([
                'region_id' => $regionId((string) ($old->region ?: 'غير محدد')),
                'name' => $old->title,
                'kind' => str_contains((string) $old->tomb_specialization, 'لحد') ? 'lahd' : 'eyes',
                'serves' => match (true) {
                    str_contains((string) $old->tomb_type, 'سيد') => 'women',
                    str_contains((string) $old->tomb_type, 'رجال') => 'men',
                    default => 'mixed',
                },
                'location' => $old->location,
                'guard_name' => $old->tomb_guard_name,
                'guard_phone' => $old->tomb_guard_number ? '0'.ltrim((string) $old->tomb_guard_number, '0') : null,
                'directory_id' => $old->id,
                'created_at' => $old->created_at ?? $now,
                'updated_at' => $old->updated_at ?? $now,
            ]);
        }

        Schema::rename('tombs', 'directory_tombs_archive');
    }
};
