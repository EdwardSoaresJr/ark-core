<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->string('appointment_capacity_basis', 32)
                ->default('limiting_resource')
                ->after('appointment_slot_minutes');
            $table->unsignedSmallInteger('appointment_scheduling_target_percent')
                ->default(100)
                ->after('appointment_capacity_basis');
            $table->string('appointment_capacity_enforcement', 16)
                ->default('warn')
                ->after('appointment_scheduling_target_percent');
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn([
                'appointment_capacity_basis',
                'appointment_scheduling_target_percent',
                'appointment_capacity_enforcement',
            ]);
        });
    }
};
