<?php

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->text('invoice_disclaimer')->nullable()->after('estimate_disclaimer');
            $table->text('authorization_language')->nullable()->after('recommendation_disclaimer');
            $table->json('customer_type_disclaimers')->nullable()->after('customer_types');
        });

        $settings = ShopSettings::query()->first();

        if (! $settings) {
            return;
        }

        $settings->forceFill([
            'invoice_disclaimer' => filled($settings->invoice_disclaimer)
                ? $settings->invoice_disclaimer
                : ShopSettings::DEFAULT_INVOICE_DISCLAIMER,
            'authorization_language' => filled($settings->authorization_language)
                ? $settings->authorization_language
                : ShopSettings::DEFAULT_AUTHORIZATION_LANGUAGE,
            'customer_type_disclaimers' => $settings->customer_type_disclaimers
                ?: ShopSettings::defaultCustomerTypeDisclaimerMap(),
        ])->save();
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->dropColumn(['invoice_disclaimer', 'authorization_language', 'customer_type_disclaimers']);
        });
    }
};
