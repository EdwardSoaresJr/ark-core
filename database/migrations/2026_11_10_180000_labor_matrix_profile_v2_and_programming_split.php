<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive / forward-only:
 * - Durable system line kind for billing-minimum fees (no description-prefix identity).
 * - Split Programming onto its own operation class so matrix rates can differ from Troubleshooting.
 * Does not backfill repair_orders.labor_pricing_profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('repair_order_lines', 'system_line_kind')) {
            Schema::table('repair_order_lines', function (Blueprint $table): void {
                $table->string('system_line_kind', 64)->nullable()->after('type');
                $table->string('system_line_scope', 64)->nullable()->after('system_line_kind');
                $table->index(['repair_order_id', 'system_line_kind', 'system_line_scope'], 'ro_lines_system_kind_idx');
            });
        }

        if (! Schema::hasColumn('shop_settings', 'custom_labor_billing_classes')) {
            Schema::table('shop_settings', function (Blueprint $table): void {
                $table->json('custom_labor_billing_classes')->nullable()->after('labor_billing_minimums');
            });
        }

        $now = now();
        $programmingClassId = DB::table('operation_classes')->where('key', 'programming')->value('id');

        if ($programmingClassId === null) {
            $sort = (int) (DB::table('operation_classes')->max('sort_order') ?? 40) + 10;
            $programmingClassId = DB::table('operation_classes')->insertGetId([
                'key' => 'programming',
                'name' => 'Programming',
                'sort_order' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $diagnosticsClassId = DB::table('operation_classes')->where('key', 'diagnostics')->value('id');

        if ($diagnosticsClassId !== null) {
            DB::table('operations')
                ->where('code', 'programming')
                ->where('operation_class_id', $diagnosticsClassId)
                ->update([
                    'operation_class_id' => $programmingClassId,
                    'updated_at' => $now,
                ]);

            $existingProgrammingPolicies = DB::table('labor_policies')
                ->where('operation_class_id', $programmingClassId)
                ->exists();

            if (! $existingProgrammingPolicies) {
                $diagnosticPolicies = DB::table('labor_policies')
                    ->where('operation_class_id', $diagnosticsClassId)
                    ->whereNull('effective_until')
                    ->get();

                foreach ($diagnosticPolicies as $policy) {
                    DB::table('labor_policies')->insert([
                        'billing_posture' => $policy->billing_posture,
                        'operation_class_id' => $programmingClassId,
                        'rate_type' => $policy->rate_type,
                        'hourly_rate_cents' => $policy->hourly_rate_cents,
                        'effective_from' => $policy->effective_from,
                        'effective_until' => null,
                        'priority' => $policy->priority,
                        'version' => 1,
                        'change_reason' => 'Split Programming from Diagnostics for labor matrix',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // Backfill durable kind for already-generated minimum fees (description was prior identity).
        if (Schema::hasColumn('repair_order_lines', 'system_line_kind')) {
            DB::table('repair_order_lines')
                ->where('type', 'fee')
                ->where('description', 'like', 'Billing minimum · %')
                ->whereNull('system_line_kind')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $scope = null;
                        if (preg_match('/^Billing minimum · (.+?)(?: · line \d+)?$/', (string) $row->description, $matches) === 1) {
                            $scope = match ($matches[1]) {
                                'Retail' => 'customer_pay',
                                'Warranty' => 'warranty_other',
                                default => strtolower(str_replace(' ', '_', $matches[1])),
                            };
                        }

                        DB::table('repair_order_lines')->where('id', $row->id)->update([
                            'system_line_kind' => 'billing_minimum',
                            'system_line_scope' => $scope,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Keep programming class and remapped operations; only drop additive columns.
        if (Schema::hasColumn('repair_order_lines', 'system_line_kind')) {
            Schema::table('repair_order_lines', function (Blueprint $table): void {
                $table->dropIndex('ro_lines_system_kind_idx');
                $table->dropColumn(['system_line_kind', 'system_line_scope']);
            });
        }
    }
};
