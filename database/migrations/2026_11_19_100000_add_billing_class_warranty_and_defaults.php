<?php

use App\Ark\Operations\EstimatePricing\BillingClassDocumentTermsMode;
use App\Ark\Operations\EstimatePricing\BillingClassWarranty;
use App\Ark\Operations\EstimatePricing\BillingClassWarrantyMode;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified billing-class defaults, structured warranty, and concern warranty overrides.
 *
 * - shop_settings.shop_warranty: shop-wide structured warranty JSON
 * - customer_types[] gains is_default, warranty_mode, warranty, document_terms_mode, document_terms
 * - repair_order_concerns warranty_inherits_class + warranty_override
 *
 * Does not rewrite historical commercial stamps or issued document snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('shop_settings', 'shop_warranty')) {
                $table->json('shop_warranty')->nullable()->after('customer_type_disclaimers');
            }
        });

        Schema::table('repair_order_concerns', function (Blueprint $table) {
            if (! Schema::hasColumn('repair_order_concerns', 'warranty_inherits_class')) {
                $table->boolean('warranty_inherits_class')->nullable()->after('billing_inherits_customer');
            }
            if (! Schema::hasColumn('repair_order_concerns', 'warranty_override')) {
                $table->json('warranty_override')->nullable()->after('warranty_inherits_class');
            }
        });

        $settings = ShopSettings::query()->first();
        if ($settings === null) {
            return;
        }

        if ($settings->shop_warranty === null) {
            $settings->forceFill([
                'shop_warranty' => BillingClassWarranty::shopDefault()->toArray(),
            ])->saveQuietly();
        }

        $disclaimers = is_array($settings->customer_type_disclaimers)
            ? $settings->customer_type_disclaimers
            : [];

        $rows = collect($settings->storedCustomerTypeRows())
            ->map(function (array $row, int $index) use ($disclaimers): array {
                $key = (string) ($row['key'] ?? '');
                $nameKey = mb_strtolower(trim((string) ($row['name'] ?? '')));
                $legacyDisclaimer = $disclaimers[$key] ?? $disclaimers[$nameKey] ?? null;

                $row['is_default'] = array_key_exists('is_default', $row)
                    ? (bool) $row['is_default']
                    : ($key === 'retail' || ($index === 0 && $key !== ''));
                $row['warranty_mode'] = $row['warranty_mode']
                    ?? ($key === 'warranty' || $key === 'repairpal'
                        ? BillingClassWarrantyMode::ClassSpecific->value
                        : BillingClassWarrantyMode::InheritShop->value);
                if (! is_array($row['warranty'] ?? null)) {
                    $row['warranty'] = match ($key) {
                        'warranty', 'repairpal' => [
                            'months' => 12,
                            'miles' => 12000,
                            'covers_parts' => true,
                            'covers_labor' => true,
                            'description' => 'Third-party warranty program coverage subject to administrator approval.',
                            'exclusions' => 'Customer remains responsible for any non-covered charges.',
                            'third_party' => true,
                        ],
                        default => BillingClassWarranty::none()->toArray(),
                    };
                }
                $row['document_terms_mode'] = $row['document_terms_mode']
                    ?? (filled($legacyDisclaimer)
                        ? BillingClassDocumentTermsMode::Append->value
                        : BillingClassDocumentTermsMode::ShopDefaults->value);
                if (! array_key_exists('document_terms', $row)) {
                    $row['document_terms'] = filled($legacyDisclaimer) ? trim((string) $legacyDisclaimer) : null;
                }

                return $row;
            })
            ->values()
            ->all();

        // Exactly one default among active rows.
        $defaultSeen = false;
        foreach ($rows as $i => $row) {
            if (! ($row['is_active'] ?? true)) {
                $rows[$i]['is_default'] = false;
                continue;
            }
            if (($row['is_default'] ?? false) && ! $defaultSeen) {
                $defaultSeen = true;
                continue;
            }
            $rows[$i]['is_default'] = false;
        }
        if (! $defaultSeen) {
            foreach ($rows as $i => $row) {
                if ($row['is_active'] ?? true) {
                    $rows[$i]['is_default'] = true;
                    break;
                }
            }
        }

        $settings->forceFill(['customer_types' => $rows])->saveQuietly();
        ShopSettings::forgetCurrent();
    }

    public function down(): void
    {
        Schema::table('repair_order_concerns', function (Blueprint $table) {
            if (Schema::hasColumn('repair_order_concerns', 'warranty_override')) {
                $table->dropColumn('warranty_override');
            }
            if (Schema::hasColumn('repair_order_concerns', 'warranty_inherits_class')) {
                $table->dropColumn('warranty_inherits_class');
            }
        });

        Schema::table('shop_settings', function (Blueprint $table) {
            if (Schema::hasColumn('shop_settings', 'shop_warranty')) {
                $table->dropColumn('shop_warranty');
            }
        });
    }
};
