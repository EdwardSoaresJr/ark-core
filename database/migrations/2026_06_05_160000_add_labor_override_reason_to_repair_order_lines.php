<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->string('labor_override_reason', 255)->nullable()->after('labor_hours_overridden');
            $table->boolean('labor_minimum_applied')->default(false)->after('labor_override_reason');
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->dropColumn([
                'labor_override_reason',
                'labor_minimum_applied',
            ]);
        });
    }
};
