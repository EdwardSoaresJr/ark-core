<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive / forward-only shop fee profile.
 * Existing ROs keep null shop_fee_profile (legacy live-settings path until stamped).
 * New ROs stamp the profile at create; fee settings changes do not rewrite stamped ROs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'shop_fee_mode')) {
                $table->string('shop_fee_mode', 16)->default('percent')->after('shop_fee_enabled');
            }
            if (! Schema::hasColumn('shop_settings', 'shop_fee_flat_cents')) {
                $table->unsignedInteger('shop_fee_flat_cents')->nullable()->after('shop_fee_rate');
            }
        });

        Schema::table('repair_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('repair_orders', 'shop_fee_profile')) {
                $table->json('shop_fee_profile')->nullable()->after('shop_fee_rules');
            }
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('repair_orders', 'shop_fee_profile')) {
                $table->dropColumn('shop_fee_profile');
            }
        });

        Schema::table('shop_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_settings', 'shop_fee_flat_cents')) {
                $table->dropColumn('shop_fee_flat_cents');
            }
            if (Schema::hasColumn('shop_settings', 'shop_fee_mode')) {
                $table->dropColumn('shop_fee_mode');
            }
        });
    }
};
