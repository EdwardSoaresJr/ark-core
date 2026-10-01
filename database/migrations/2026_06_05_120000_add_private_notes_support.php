<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('part_number');
        });

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->boolean('default_notes_private')->default(true)->after('default_concern_priority');
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->dropColumn('is_private');
        });

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn('default_notes_private');
        });
    }
};
