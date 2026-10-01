<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('workstation_id')
                ->nullable()
                ->after('technician_user_id')
                ->constrained('workstations')
                ->nullOnDelete();
            $table->decimal('estimated_labor_hours', 5, 2)
                ->nullable()
                ->after('ends_at');
            $table->index(['workstation_id', 'starts_at'], 'appt_workstation_starts');
            $table->index(['technician_user_id', 'starts_at'], 'appt_tech_starts');
        });

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->json('scheduling_hours')->nullable()->after('appointments_enabled');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('scheduling_hours')->nullable()->after('workday_hours');
        });

        Schema::table('workstations', function (Blueprint $table) {
            $table->boolean('accepts_scheduled_work')
                ->default(false)
                ->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appt_workstation_starts');
            $table->dropIndex('appt_tech_starts');
            $table->dropConstrainedForeignId('workstation_id');
            $table->dropColumn('estimated_labor_hours');
        });

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn('scheduling_hours');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('scheduling_hours');
        });

        Schema::table('workstations', function (Blueprint $table) {
            $table->dropColumn('accepts_scheduled_work');
        });
    }
};
