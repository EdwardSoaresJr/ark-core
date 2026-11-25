<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inspection obligation is optional by default.
 * Additive only — existing shops and ROs keep optional closeout behavior.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_settings') && ! Schema::hasColumn('shop_settings', 'inspection_lifecycle_policy')) {
            Schema::table('shop_settings', function (Blueprint $table): void {
                $table->string('inspection_lifecycle_policy', 32)->default('optional');
            });
        }

        if (Schema::hasTable('repair_orders')) {
            Schema::table('repair_orders', function (Blueprint $table): void {
                if (! Schema::hasColumn('repair_orders', 'inspection_lifecycle_policy')) {
                    $table->string('inspection_lifecycle_policy', 32)->nullable();
                }
                if (! Schema::hasColumn('repair_orders', 'inspection_waived_at')) {
                    $table->timestamp('inspection_waived_at')->nullable();
                }
                if (! Schema::hasColumn('repair_orders', 'inspection_waived_by')) {
                    $table->foreignId('inspection_waived_by')->nullable();
                }
                if (! Schema::hasColumn('repair_orders', 'inspection_waived_reason')) {
                    $table->string('inspection_waived_reason', 1000)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('repair_orders')) {
            Schema::table('repair_orders', function (Blueprint $table): void {
                foreach (['inspection_waived_reason', 'inspection_waived_by', 'inspection_waived_at', 'inspection_lifecycle_policy'] as $column) {
                    if (Schema::hasColumn('repair_orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('shop_settings') && Schema::hasColumn('shop_settings', 'inspection_lifecycle_policy')) {
            Schema::table('shop_settings', function (Blueprint $table): void {
                $table->dropColumn('inspection_lifecycle_policy');
            });
        }
    }
};
