<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_concerns', function (Blueprint $table): void {
            $table->string('production_status', 32)->default('pending')->after('disposition');
            $table->index(['repair_order_id', 'production_status'], 'ro_concerns_ro_prod_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_concerns', function (Blueprint $table): void {
            $table->dropIndex('ro_concerns_ro_prod_status_idx');
            $table->dropColumn('production_status');
        });
    }
};
