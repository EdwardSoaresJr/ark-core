<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->unsignedInteger('mileage_in')->nullable()->after('concern_summary');
            $table->unsignedInteger('mileage_out')->nullable()->after('mileage_in');
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropColumn(['mileage_in', 'mileage_out']);
        });
    }
};
