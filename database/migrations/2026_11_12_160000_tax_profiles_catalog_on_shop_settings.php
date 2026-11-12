<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice A (#174): shop-level named tax profiles / components catalog.
 * Additive and forward-only — does not rewrite repair_orders.tax_profile or line tax cents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->json('tax_profiles')->nullable()->after('taxable_shop_fees');
            $table->string('tax_document_presentation', 16)->default('combined')->after('tax_profiles');
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->dropColumn(['tax_profiles', 'tax_document_presentation']);
        });
    }
};
