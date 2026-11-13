<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #194 R1 + D2:
 * - Add shop_settings.labor_rounding (Work Type × Billing Class).
 * - Backfill from labor_categories[].rounding_rule so shops keep current behavior.
 * - Freeze open Standard labor policies to explicit Hourly/Zero with stored cents.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_settings') && ! Schema::hasColumn('shop_settings', 'labor_rounding')) {
            Schema::table('shop_settings', function (Blueprint $table): void {
                $table->json('labor_rounding')->nullable()->after('labor_billing_minimums');
            });
        }

        if (Schema::hasTable('shop_settings') && Schema::hasColumn('shop_settings', 'labor_rounding')) {
            $settings = DB::table('shop_settings')->orderBy('id')->first();

            if ($settings !== null) {
                $categories = json_decode((string) ($settings->labor_categories ?? '[]'), true);
                $categories = is_array($categories) ? $categories : [];
                $categoryRounding = [];

                foreach ($categories as $category) {
                    if (! is_array($category) || ! isset($category['key'])) {
                        continue;
                    }

                    $categoryRounding[(string) $category['key']] = (string) ($category['rounding_rule'] ?? 'quarter');
                }

                $customClasses = json_decode((string) ($settings->custom_labor_billing_classes ?? '[]'), true);
                $customClasses = is_array($customClasses) ? $customClasses : [];

                $workTypes = [
                    'mechanical' => 'mechanical',
                    'advanced_mechanical' => 'advanced_mechanical',
                    'diagnostic' => 'diagnostic',
                    'programming' => 'programming',
                    'body' => 'body',
                    'sublet' => 'sublet',
                    'courtesy' => 'courtesy',
                    'comeback' => 'comeback',
                    'repairpal' => 'repairpal',
                    'warranty_other' => 'warranty_other',
                ];

                foreach ($categories as $category) {
                    if (! is_array($category) || ! isset($category['key'])) {
                        continue;
                    }
                    $key = (string) $category['key'];
                    $workTypes[$key] = $key;
                }

                $billingClasses = [
                    'customer_pay',
                    'warranty_other',
                    'fleet',
                    'repairpal',
                    'wholesale',
                    'internal',
                    'comeback',
                ];

                foreach ($customClasses as $class) {
                    if (is_array($class) && isset($class['key']) && is_string($class['key']) && $class['key'] !== '') {
                        $billingClasses[] = $class['key'];
                    }
                }

                $matrix = [];

                foreach ($workTypes as $workType => $categoryKey) {
                    $rule = $categoryRounding[$categoryKey]
                        ?? $categoryRounding[$workType]
                        ?? 'quarter';

                    if (! in_array($rule, ['exact', 'tenth', 'quarter', 'half'], true)) {
                        $rule = 'quarter';
                    }

                    foreach (array_unique($billingClasses) as $billingClass) {
                        $matrix[$workType][$billingClass] = $rule;
                    }
                }

                // Prefer LaborWorkTypeMatrix work-type keys when operation classes exist.
                if (Schema::hasTable('operation_classes')) {
                    $ops = DB::table('operation_classes')->pluck('key')->all();
                    $map = [
                        'general_repair' => 'mechanical',
                        'advanced_mechanical' => 'advanced_mechanical',
                        'diagnostics' => 'diagnostic',
                        'programming' => 'programming',
                    ];
                    foreach ($map as $op => $wt) {
                        if (in_array($op, $ops, true) && ! isset($matrix[$wt])) {
                            $rule = $categoryRounding[$wt] ?? $categoryRounding[str_replace('diagnostic', 'diagnostic', $wt)] ?? 'quarter';
                            foreach (array_unique($billingClasses) as $billingClass) {
                                $matrix[$wt][$billingClass] = $rule;
                            }
                        }
                    }
                }

                DB::table('shop_settings')->where('id', $settings->id)->update([
                    'labor_rounding' => json_encode($matrix),
                    'updated_at' => now(),
                ]);
            }
        }

        if (! Schema::hasTable('labor_policies') || ! Schema::hasTable('shop_settings')) {
            return;
        }

        $defaultCents = (int) (DB::table('shop_settings')->value('default_labor_rate_cents') ?: 16500);

        $standardPolicies = DB::table('labor_policies')
            ->where('rate_type', 'standard')
            ->whereNull('effective_until')
            ->get(['id', 'hourly_rate_cents']);

        foreach ($standardPolicies as $policy) {
            $cents = $policy->hourly_rate_cents !== null
                ? (int) $policy->hourly_rate_cents
                : $defaultCents;

            DB::table('labor_policies')->where('id', $policy->id)->update([
                'rate_type' => $cents <= 0 ? 'zero' : 'hourly',
                'hourly_rate_cents' => $cents,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shop_settings') && Schema::hasColumn('shop_settings', 'labor_rounding')) {
            Schema::table('shop_settings', function (Blueprint $table): void {
                $table->dropColumn('labor_rounding');
            });
        }

        // Do not reverse Standard→Hourly freeze; that would reintroduce inheritance.
    }
};
