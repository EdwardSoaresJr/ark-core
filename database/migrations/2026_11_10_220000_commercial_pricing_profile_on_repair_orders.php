<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive / forward-only commercial (billing-class) pricing profile.
 * Existing ROs keep null until stamped on the next Billing Classes settings save
 * (pre-change snapshot) or until explicitly restamped by an advisor customer-class change.
 * New ROs stamp at create. Closed RO amounts are never rewritten by this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'commercial_pricing_profile_version')) {
                $table->unsignedInteger('commercial_pricing_profile_version')->default(1)->after('labor_pricing_profile_version');
            }
        });

        Schema::table('repair_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('repair_orders', 'commercial_pricing_profile')) {
                $table->json('commercial_pricing_profile')->nullable()->after('shop_fee_profile');
            }
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('repair_orders', 'commercial_pricing_profile')) {
                $table->dropColumn('commercial_pricing_profile');
            }
        });

        Schema::table('shop_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_settings', 'commercial_pricing_profile_version')) {
                $table->dropColumn('commercial_pricing_profile_version');
            }
        });
    }
};
