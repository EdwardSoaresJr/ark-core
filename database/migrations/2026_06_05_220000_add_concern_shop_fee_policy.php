<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_concerns', function (Blueprint $table) {
            $table->string('shop_fee_policy', 16)->default('default')->after('disposition');
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_concerns', function (Blueprint $table) {
            $table->dropColumn('shop_fee_policy');
        });
    }
};
