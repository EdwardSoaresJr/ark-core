<?php

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = DB::table('shop_settings')->first();

        if ($settings === null) {
            return;
        }

        $categories = json_decode((string) ($settings->labor_categories ?? '[]'), true);

        if (! is_array($categories) || $categories === []) {
            $categories = ShopSettings::DEFAULT_LABOR_CATEGORIES;
        }

        $categories = collect($categories)
            ->map(function (array $category): array {
                if (! array_key_exists('allows_modifiers', $category)) {
                    $category['allows_modifiers'] = ! in_array(
                        (string) $category['key'],
                        ShopSettings::LABOR_MODIFIER_LOCKED_BY_DEFAULT_CATEGORY_KEYS,
                        true,
                    );
                }

                return $category;
            })
            ->values()
            ->all();

        DB::table('shop_settings')->update([
            'labor_categories' => json_encode($categories),
        ]);
    }

    public function down(): void
    {
        $settings = DB::table('shop_settings')->first();

        if ($settings === null) {
            return;
        }

        $categories = json_decode((string) ($settings->labor_categories ?? '[]'), true);

        if (! is_array($categories)) {
            return;
        }

        $categories = collect($categories)
            ->map(function (array $category): array {
                unset($category['allows_modifiers']);

                return $category;
            })
            ->values()
            ->all();

        DB::table('shop_settings')->update([
            'labor_categories' => json_encode($categories),
        ]);
    }
};
