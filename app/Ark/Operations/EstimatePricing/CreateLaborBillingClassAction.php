<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates a shop-scoped billing class with its own labor_policies key.
 * Fee/parts special behavior falls back to a chosen built-in base_posture.
 */
final class CreateLaborBillingClassAction
{
    /**
     * @return array{key: string, label: string, base_posture: string}
     */
    public function execute(string $name, string $basePosture, ShopSettings $settings): array
    {
        $label = trim($name);

        if ($label === '') {
            throw ValidationException::withMessages([
                'name' => 'Enter a billing class name.',
            ]);
        }

        if (mb_strlen($label) > 48) {
            throw ValidationException::withMessages([
                'name' => 'Billing class names must be 48 characters or fewer.',
            ]);
        }

        $base = ConcernBillingPosture::tryFrom($basePosture) ?? ConcernBillingPosture::CustomerPay;

        if (in_array($base, [ConcernBillingPosture::Warranty, ConcernBillingPosture::WarrantyRepairPal, ConcernBillingPosture::Default], true)) {
            $base = ConcernBillingPosture::CustomerPay;
        }

        $existingKeys = collect(LaborWorkTypeMatrix::matrixBillingColumns($settings))->pluck('value')->all();
        $classes = collect(is_array($settings->custom_labor_billing_classes) ? $settings->custom_labor_billing_classes : [])
            ->filter(fn ($row): bool => is_array($row))
            ->values()
            ->all();

        if (count($classes) >= 12) {
            throw ValidationException::withMessages([
                'name' => 'This shop already has the maximum number of custom billing classes.',
            ]);
        }

        $key = 'custom_'.Str::slug($label, '_');
        $key = mb_substr($key !== 'custom_' ? $key : 'custom_'.Str::lower(Str::random(6)), 0, 24);

        if (in_array($key, $existingKeys, true)
            || collect($classes)->contains(fn (array $row): bool => ($row['key'] ?? '') === $key
                || mb_strtolower((string) ($row['label'] ?? '')) === mb_strtolower($label))) {
            throw ValidationException::withMessages([
                'name' => 'A billing class with that name already exists.',
            ]);
        }

        return DB::transaction(function () use ($settings, $label, $key, $base, $classes): array {
            $classes[] = [
                'key' => $key,
                'label' => $label,
                'base_posture' => $base->value,
                'is_active' => true,
            ];

            $settings->update([
                'custom_labor_billing_classes' => $classes,
                'labor_pricing_profile_version' => max(1, (int) ($settings->labor_pricing_profile_version ?? 0) + 1),
            ]);

            $defaultCents = $settings->defaultLaborRateCents();
            $effectiveFrom = Carbon::now()->startOfDay()->toDateString();

            foreach (OperationClass::query()->get() as $operationClass) {
                LaborPolicy::query()->create([
                    'billing_posture' => $key,
                    'operation_class_id' => $operationClass->id,
                    'rate_type' => LaborRateType::Hourly,
                    'hourly_rate_cents' => $defaultCents,
                    'effective_from' => $effectiveFrom,
                    'effective_until' => null,
                    'priority' => 100,
                    'version' => 1,
                    'change_reason' => 'New billing class',
                ]);
            }

            $minimums = LaborBillingMinimums::fromStored(
                is_array($settings->labor_billing_minimums) ? $settings->labor_billing_minimums : null,
            )->toArray();
            $minimums[$key] = [
                'mode' => LaborBillingMinimumMode::None->value,
                'hours' => '0.00',
                'shortfall_rate_cents' => $defaultCents,
                'shortfall_rate_source' => LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
            ];
            $settings->update(['labor_billing_minimums' => $minimums]);

            return [
                'key' => $key,
                'label' => $label,
                'base_posture' => $base->value,
            ];
        });
    }
}
