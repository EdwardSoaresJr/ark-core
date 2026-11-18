<?php

use App\Ark\Operations\EstimatePricing\CreateCanonicalBillingClassAction;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * #217 billing-class consolidation.
 *
 * - repair_order_concerns.billing_inherits_customer: true where the concern was on shop default;
 *   NULL everywhere else (NULL with a non-default posture is read as an explicit override, so
 *   ambiguous financial history is never rewritten).
 * - customers.billing_class_key: stable key resolved from the existing display name; unmatched
 *   names stay NULL and resolve by name at runtime.
 * - Legacy customer-only classes get a linked custom labor column. Name collisions with an
 *   existing labor-only column are preserved and logged (billing_class_collision), never merged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_concerns', function (Blueprint $table) {
            $table->boolean('billing_inherits_customer')->nullable()->after('billing_posture');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('billing_class_key', 64)->nullable()->after('customer_type');
        });

        DB::table('repair_order_concerns')
            ->where('billing_posture', 'default')
            ->update(['billing_inherits_customer' => true]);

        $settings = ShopSettings::query()->first();
        if ($settings === null) {
            return;
        }

        ShopSettings::bindCurrent($settings);

        try {
            $result = app(CreateCanonicalBillingClassAction::class)->linkLegacyCustomerOnlyClasses($settings);
            Log::info('billing_class_legacy_links', $result);
        } catch (Throwable $e) {
            // Labor tables may be absent on very old databases; classes then stay unlinked and price at Retail.
            Log::warning('billing_class_legacy_link_skipped', ['message' => $e->getMessage()]);
        }

        ShopSettings::forgetCurrent();
        $settings = ShopSettings::query()->first();

        $seen = [];
        foreach ($settings->customerTypeRows() as $row) {
            $name = mb_strtolower(trim((string) $row['name']));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            DB::table('customers')
                ->whereNull('billing_class_key')
                ->whereRaw('LOWER(TRIM(customer_type)) = ?', [$name])
                ->update(['billing_class_key' => $row['key']]);
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('billing_class_key');
        });

        Schema::table('repair_order_concerns', function (Blueprint $table) {
            $table->dropColumn('billing_inherits_customer');
        });
    }
};
