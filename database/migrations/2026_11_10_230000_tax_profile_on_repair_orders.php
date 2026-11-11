<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive / forward-only tax profile.
 * Existing ROs keep null until stamped on the next Tax settings save (pre-change snapshot)
 * or at create for new ROs. Closed amounts are never rewritten by this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('repair_orders', 'tax_profile')) {
                $table->json('tax_profile')->nullable()->after('commercial_pricing_profile');
            }
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('repair_orders', 'tax_profile')) {
                $table->dropColumn('tax_profile');
            }
        });
    }
};
