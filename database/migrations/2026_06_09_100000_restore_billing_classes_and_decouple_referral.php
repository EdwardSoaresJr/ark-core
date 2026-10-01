<?php

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('repair_order_concerns')
            ->where('billing_posture', 'warranty_repairpal')
            ->update(['billing_posture' => 'repairpal']);

        $settings = DB::table('shop_settings')->first();

        if ($settings === null) {
            return;
        }

        $customerTypes = json_decode((string) ($settings->customer_types ?? '[]'), true);

        if (! is_array($customerTypes)) {
            $customerTypes = ShopSettings::DEFAULT_CUSTOMER_TYPES;
        }

        $existingNames = collect($customerTypes)
            ->pluck('name')
            ->filter()
            ->map(fn (mixed $name): string => mb_strtolower((string) $name))
            ->all();

        foreach (ShopSettings::DEFAULT_CUSTOMER_TYPES as $defaultRow) {
            $name = (string) ($defaultRow['name'] ?? '');

            if ($name === '' || in_array(mb_strtolower($name), $existingNames, true)) {
                continue;
            }

            $customerTypes[] = $defaultRow;
            $existingNames[] = mb_strtolower($name);
        }

        $categories = json_decode((string) ($settings->labor_categories ?? '[]'), true);

        if (! is_array($categories)) {
            $categories = ShopSettings::DEFAULT_LABOR_CATEGORIES;
        }

        $existingKeys = collect($categories)->pluck('key')->filter()->all();

        foreach (ShopSettings::DEFAULT_LABOR_CATEGORIES as $defaultCategory) {
            $key = (string) ($defaultCategory['key'] ?? '');

            if ($key === 'warranty-repairpal') {
                foreach ($categories as $index => $category) {
                    if (($category['key'] ?? null) === 'warranty-repairpal') {
                        $categories[$index]['key'] = ShopSettings::WARRANTY_REPAIRPAL_LABOR_CATEGORY_KEY;
                        $categories[$index]['name'] = 'RepairPal';
                    }
                }

                continue;
            }

            if (! in_array($key, $existingKeys, true)) {
                $categories[] = $defaultCategory;
                $existingKeys[] = $key;
            }
        }

        $disclaimers = json_decode((string) ($settings->customer_type_disclaimers ?? '[]'), true);

        if (! is_array($disclaimers)) {
            $disclaimers = ShopSettings::defaultCustomerTypeDisclaimerMap();
        }

        foreach (ShopSettings::defaultCustomerTypeDisclaimerMap() as $key => $text) {
            $disclaimers[$key] ??= $text;
        }

        DB::table('shop_settings')->update([
            'customer_types' => json_encode(array_values($customerTypes)),
            'labor_categories' => json_encode(array_values($categories)),
            'customer_type_disclaimers' => json_encode($disclaimers),
        ]);
    }

    public function down(): void
    {
        DB::table('repair_order_concerns')
            ->where('billing_posture', 'repairpal')
            ->update(['billing_posture' => 'warranty_repairpal']);
    }
};
