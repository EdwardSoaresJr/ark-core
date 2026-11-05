<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->string('parts_cart_source_key', 128)->nullable()->after('dealer_quote_line_id');
            $table->json('parts_cart_snapshot')->nullable()->after('parts_cart_source_key');
            $table->index(['repair_order_id', 'parts_cart_source_key'], 'ro_lines_parts_cart_source_idx');
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->dropIndex('ro_lines_parts_cart_source_idx');
            $table->dropColumn(['parts_cart_source_key', 'parts_cart_snapshot']);
        });
    }
};
