<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shop-owned public Business Hours column.
 *
 * Existing shops keep working through ShopSettings::businessHoursSnapshot() legacy
 * fallback until staff save Business Hours once (which writes this column).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_settings') || Schema::hasColumn('shop_settings', 'business_hours')) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_settings', 'shop_timezone')) {
                $table->json('business_hours')->nullable()->after('shop_timezone');
            } else {
                $table->json('business_hours')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('shop_settings') && Schema::hasColumn('shop_settings', 'business_hours')) {
            Schema::table('shop_settings', function (Blueprint $table): void {
                $table->dropColumn('business_hours');
            });
        }
    }
};
