<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Null keeps every existing repair order on pooled shop-fee allocation and the shop-wide
     * fee rule for Shop default concerns. Repair orders created afterwards are stamped with
     * per-line fee rules when they are created; nothing is backfilled.
     */
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('shop_fee_rules')->nullable()->after('default_billing_posture');
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropColumn('shop_fee_rules');
        });
    }
};
