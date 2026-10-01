<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'default_deposit_enabled')) {
                $table->boolean('default_deposit_enabled')->default(true)->after('shop_fee_cap_cents');
            }

            if (! Schema::hasColumn('shop_settings', 'default_deposit_include_parts')) {
                $table->boolean('default_deposit_include_parts')->default(true)->after('default_deposit_enabled');
            }

            if (! Schema::hasColumn('shop_settings', 'default_deposit_include_diagnostics')) {
                $table->boolean('default_deposit_include_diagnostics')->default(true)->after('default_deposit_include_parts');
            }

            if (! Schema::hasColumn('shop_settings', 'default_deposit_diagnostic_labor_category_keys')) {
                $table->json('default_deposit_diagnostic_labor_category_keys')->nullable()->after('default_deposit_include_diagnostics');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'default_deposit_enabled',
                'default_deposit_include_parts',
                'default_deposit_include_diagnostics',
                'default_deposit_diagnostic_labor_category_keys',
            ]);
        });
    }
};
