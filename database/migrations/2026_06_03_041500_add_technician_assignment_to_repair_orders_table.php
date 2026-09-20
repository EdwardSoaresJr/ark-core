<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->foreignId('assigned_technician_id')
                ->nullable()
                ->after('vehicle_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['assigned_technician_id', 'status'], 'ro_tech_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropForeign(['assigned_technician_id']);
            $table->dropIndex('ro_tech_status_idx');
            $table->dropColumn('assigned_technician_id');
        });
    }
};
