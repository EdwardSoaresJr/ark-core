<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->string('customer_part_description_mode', 32)->default('cleaned')->after('portal_signature_required');
            $table->boolean('customer_part_show_manufacturer_number')->default(false)->after('customer_part_description_mode');
            $table->boolean('customer_part_show_supplier')->default(false)->after('customer_part_show_manufacturer_number');
            $table->boolean('customer_part_show_supplier_sku')->default(false)->after('customer_part_show_supplier');
            $table->boolean('customer_part_allow_description_override')->default(true)->after('customer_part_show_supplier_sku');
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn([
                'customer_part_description_mode',
                'customer_part_show_manufacturer_number',
                'customer_part_show_supplier',
                'customer_part_show_supplier_sku',
                'customer_part_allow_description_override',
            ]);
        });
    }
};
