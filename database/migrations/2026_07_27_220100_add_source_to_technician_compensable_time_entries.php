<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_compensable_time_entries', function (Blueprint $table) {
            $table->string('source', 32)->default('manual_override')->after('compensable_hours');
            $table->boolean('manual_locked')->default(true)->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('technician_compensable_time_entries', function (Blueprint $table) {
            $table->dropColumn(['source', 'manual_locked']);
        });
    }
};
