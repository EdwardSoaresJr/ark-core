<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repair_orders')) {
            return;
        }

        if (! Schema::hasColumn('repair_orders', 'companion_suggestion_dismissed_hash')) {
            Schema::table('repair_orders', function (Blueprint $table) {
                $table->char('companion_suggestion_dismissed_hash', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('repair_orders') || ! Schema::hasColumn('repair_orders', 'companion_suggestion_dismissed_hash')) {
            return;
        }

        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropColumn('companion_suggestion_dismissed_hash');
        });
    }
};
