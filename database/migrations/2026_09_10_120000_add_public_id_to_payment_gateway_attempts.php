<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateway_attempts', function (Blueprint $table) {
            $table->uuid('public_id')->nullable()->unique('pay_gw_attempt_public_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_attempts', function (Blueprint $table) {
            $table->dropUnique('pay_gw_attempt_public_id_unique');
            $table->dropColumn('public_id');
        });
    }
};
