<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table): void {
            $table->integer('standing_discount_cents')->default(0)->after('shop_fee_cents');
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table): void {
            $table->dropColumn('standing_discount_cents');
        });
    }
};
