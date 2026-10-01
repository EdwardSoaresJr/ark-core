<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->json('labor_categories')->nullable()->after('default_labor_rate_cents');
        });

        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->string('labor_category_key', 64)->nullable()->after('is_overridden');
            $table->string('labor_category_name', 120)->nullable()->after('labor_category_key');
            $table->decimal('labor_entered_hours', 8, 2)->nullable()->after('labor_category_name');
            $table->string('labor_adjustment', 32)->nullable()->after('labor_entered_hours');
            $table->decimal('labor_adjustment_factor', 5, 2)->nullable()->after('labor_adjustment');
            $table->string('labor_adjustment_reason', 255)->nullable()->after('labor_adjustment_factor');
            $table->decimal('labor_billed_hours', 8, 2)->nullable()->after('labor_adjustment_reason');
            $table->boolean('labor_hours_overridden')->default(false)->after('labor_billed_hours');
            $table->unsignedInteger('labor_rate_cents')->nullable()->after('labor_hours_overridden');
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->dropColumn([
                'labor_category_key',
                'labor_category_name',
                'labor_entered_hours',
                'labor_adjustment',
                'labor_adjustment_factor',
                'labor_adjustment_reason',
                'labor_billed_hours',
                'labor_hours_overridden',
                'labor_rate_cents',
            ]);
        });

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn('labor_categories');
        });
    }
};
