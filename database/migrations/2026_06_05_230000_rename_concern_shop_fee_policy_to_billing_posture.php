<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_concerns', function (Blueprint $table): void {
            $table->renameColumn('shop_fee_policy', 'billing_posture');
        });

        DB::table('repair_order_concerns')->where('billing_posture', 'inherit')->update(['billing_posture' => 'default']);
        DB::table('repair_order_concerns')->where('billing_posture', 'apply')->update(['billing_posture' => 'customer_pay']);
        DB::table('repair_order_concerns')->where('billing_posture', 'waive')->update(['billing_posture' => 'warranty']);
    }

    public function down(): void
    {
        DB::table('repair_order_concerns')->where('billing_posture', 'default')->update(['billing_posture' => 'inherit']);
        DB::table('repair_order_concerns')->where('billing_posture', 'customer_pay')->update(['billing_posture' => 'apply']);
        DB::table('repair_order_concerns')->where('billing_posture', 'warranty')->update(['billing_posture' => 'waive']);
        DB::table('repair_order_concerns')->where('billing_posture', 'fleet')->update(['billing_posture' => 'inherit']);

        Schema::table('repair_order_concerns', function (Blueprint $table): void {
            $table->renameColumn('billing_posture', 'shop_fee_policy');
        });
    }
};
