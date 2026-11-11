<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Explicitly mark Customer Pay policies that already equal the shop standard rate
 * as rate_type=standard so inheritance is stored, not inferred from matching amounts later.
 * Specialty / zero / contract policies are left unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('labor_policies') || ! Schema::hasTable('shop_settings')) {
            return;
        }

        $defaultCents = (int) (DB::table('shop_settings')->value('default_labor_rate_cents') ?: 16500);

        DB::table('labor_policies')
            ->where('billing_posture', 'customer_pay')
            ->where('rate_type', 'hourly')
            ->where('hourly_rate_cents', $defaultCents)
            ->whereNull('effective_until')
            ->update([
                'rate_type' => 'standard',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('labor_policies')) {
            return;
        }

        DB::table('labor_policies')
            ->where('rate_type', 'standard')
            ->update([
                'rate_type' => 'hourly',
                'updated_at' => now(),
            ]);
    }
};
