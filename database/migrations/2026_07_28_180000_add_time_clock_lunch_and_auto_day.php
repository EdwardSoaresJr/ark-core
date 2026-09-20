<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_time_sessions', function (Blueprint $table) {
            $table->string('close_reason', 32)->nullable()->after('status');
            $table->string('origin', 32)->nullable()->after('close_reason');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('auto_clock_enabled')->default(false)->after('scheduling_hours');
            $table->unsignedSmallInteger('auto_lunch_minutes')->nullable()->after('auto_clock_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['auto_clock_enabled', 'auto_lunch_minutes']);
        });

        Schema::table('technician_time_sessions', function (Blueprint $table) {
            $table->dropColumn(['close_reason', 'origin']);
        });
    }
};
