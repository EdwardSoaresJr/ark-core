<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forward-only labor pricing profiles + billing-class minimums config.
 *
 * Existing repair_orders keep labor_pricing_profile = null (legacy: no billing-class minimums).
 * New ROs stamp a snapshot at create time from shop_settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->unsignedInteger('labor_pricing_profile_version')->default(0)->after('default_labor_rate_cents');
            $table->json('labor_billing_minimums')->nullable()->after('labor_pricing_profile_version');
        });

        Schema::table('repair_orders', function (Blueprint $table): void {
            $table->json('labor_pricing_profile')->nullable()->after('shop_fee_rules');
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table): void {
            $table->dropColumn('labor_pricing_profile');
        });

        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->dropColumn(['labor_pricing_profile_version', 'labor_billing_minimums']);
        });
    }
};
