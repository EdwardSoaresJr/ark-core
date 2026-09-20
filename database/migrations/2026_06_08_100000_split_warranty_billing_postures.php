<?php

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('repair_order_concerns')
            ->where('billing_posture', 'warranty')
            ->update(['billing_posture' => 'warranty_other']);

        $settings = DB::table('shop_settings')->first();

        if ($settings === null) {
            return;
        }

        $categories = json_decode((string) ($settings->labor_categories ?? '[]'), true);

        if (! is_array($categories)) {
            $categories = ShopSettings::DEFAULT_LABOR_CATEGORIES;
        }

        $existingKeys = collect($categories)->pluck('key')->filter()->all();

        foreach (ShopSettings::DEFAULT_LABOR_CATEGORIES as $defaultCategory) {
            if (! in_array($defaultCategory['key'], $existingKeys, true)) {
                $categories[] = $defaultCategory;
            }
        }

        DB::table('shop_settings')->update([
            'labor_categories' => json_encode(array_values($categories)),
        ]);
    }

    public function down(): void
    {
        DB::table('repair_order_concerns')
            ->whereIn('billing_posture', ['warranty_repairpal', 'warranty_other'])
            ->update(['billing_posture' => 'warranty']);
    }
};
