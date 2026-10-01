<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->string('procurement_state', 32)->default('none')->after('part_number');

            $table->index(['repair_order_id', 'type', 'procurement_state'], 'ro_lines_procurement_idx');
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->dropIndex('ro_lines_procurement_idx');
            $table->dropColumn('procurement_state');
        });
    }
};
