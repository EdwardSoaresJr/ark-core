<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shop fee labor/parts eligibility (labor | parts | both).
 * Additive only. Existing ROs keep stamped shop_fee_profile without applies_to
 * and continue using the historical Labor+Part+Package eligible set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'shop_fee_applies_to')) {
                $table->string('shop_fee_applies_to', 16)->default('both')->after('shop_fee_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_settings', 'shop_fee_applies_to')) {
                $table->dropColumn('shop_fee_applies_to');
            }
        });
    }
};
