<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->string('part_source', 24)->default('shop_supplied')->after('sourcing_notes');
            $table->string('part_classification', 32)->nullable()->after('part_source');
            $table->string('part_warranty_impact', 24)->default('none')->after('part_classification');
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->dropColumn(['part_source', 'part_classification', 'part_warranty_impact']);
        });
    }
};
