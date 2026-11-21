<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which member wrote a comment, so only that member can edit or delete it.
     * Older comments keep a null owner and can no longer be changed through the API.
     */
    public function up(): void
    {
        Schema::table('news_comments', function (Blueprint $table) {
            // subscribers.id is a signed INT (not BIGINT), so the column must match it.
            $table->integer('subscriber_id')->nullable()->after('news_id');
            $table->foreign('subscriber_id')->references('id')->on('subscribers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('news_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscriber_id');
        });
    }
};
