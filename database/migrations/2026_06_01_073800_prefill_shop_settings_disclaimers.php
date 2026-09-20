<?php

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ShopSettings::query()
            ->where(function ($query) {
                $query->whereNull('estimate_disclaimer')
                    ->orWhere('estimate_disclaimer', '');
            })
            ->update(['estimate_disclaimer' => ShopSettings::DEFAULT_ESTIMATE_DISCLAIMER]);

        ShopSettings::query()
            ->where(function ($query) {
                $query->whereNull('recommendation_disclaimer')
                    ->orWhere('recommendation_disclaimer', '');
            })
            ->update(['recommendation_disclaimer' => ShopSettings::DEFAULT_RECOMMENDATION_DISCLAIMER]);
    }

    public function down(): void
    {
        ShopSettings::query()
            ->where('estimate_disclaimer', ShopSettings::DEFAULT_ESTIMATE_DISCLAIMER)
            ->update(['estimate_disclaimer' => null]);

        ShopSettings::query()
            ->where('recommendation_disclaimer', ShopSettings::DEFAULT_RECOMMENDATION_DISCLAIMER)
            ->update(['recommendation_disclaimer' => null]);
    }
};
