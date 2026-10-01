<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->json('shop_overhead_state')->nullable()->after('customer_type_disclaimers');
            $table->unsignedInteger('shop_overhead_per_hour_cents')->nullable()->after('shop_overhead_state');
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn(['shop_overhead_state', 'shop_overhead_per_hour_cents']);
        });
    }
};
