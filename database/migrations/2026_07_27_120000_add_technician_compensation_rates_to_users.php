<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('flag_rate_cents')->nullable()->after('labor_pay_basis');
            $table->unsignedInteger('floor_rate_cents')->nullable()->after('flag_rate_cents');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['flag_rate_cents', 'floor_rate_cents']);
        });
    }
};
