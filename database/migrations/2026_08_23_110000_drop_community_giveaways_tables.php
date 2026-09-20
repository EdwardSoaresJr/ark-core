<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('community_giveaways') && Schema::hasColumn('community_giveaways', 'winner_entry_id')) {
            Schema::table('community_giveaways', function ($table): void {
                $table->dropForeign(['winner_entry_id']);
            });
        }

        Schema::dropIfExists('community_giveaway_entries');
        Schema::dropIfExists('community_giveaways');
    }

    public function down(): void
    {
        // Community Giveaways are retired. Recreate from 2026_07_28 giveaway migrations if needed.
    }
};
