<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\EstimatePricing\CustomerBillingClassIdentity;
use App\Ark\Operations\EstimatePricing\LaborPolicy;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Archive / restore / delete for custom labor matrix columns (custom_labor_billing_classes).
 *
 * Built-in ConcernBillingPosture columns are never deleted or archived here — they are
 * platform postures, not shop-owned configuration rows. Customer Billing Classes that
 * link to a labor column are a separate identity (CustomerBillingClassLifecycle).
 */
final class CustomLaborBillingClassLifecycle
{
    /**
     * @return list<array{code: string, label: string, count: int}>
     */
    public function references(string $laborColumnKey): array
    {
        $refs = [];

        $concernCount = RepairOrderConcern::query()
            ->where('billing_posture', $laborColumnKey)
            ->count();
        if ($concernCount > 0) {
            $refs[] = [
                'code' => 'concerns',
                'label' => $concernCount === 1
                    ? '1 concern bills with this labor column'
                    : "{$concernCount} concerns bill with this labor column",
                'count' => $concernCount,
            ];
        }

        $roDefaultCount = RepairOrder::query()
            ->where('default_billing_posture', $laborColumnKey)
            ->count();
        if ($roDefaultCount > 0) {
            $refs[] = [
                'code' => 'repair_order_defaults',
                'label' => $roDefaultCount === 1
                    ? '1 repair order defaults to this labor column'
                    : "{$roDefaultCount} repair orders default to this labor column",
                'count' => $roDefaultCount,
            ];
        }

        return $refs;
    }

    /**
     * @return array{key: string, is_active: bool}
     */
    public function archive(ShopSettings $settings, string $laborColumnKey): array
    {
        return $this->setActive($settings, $laborColumnKey, false);
    }

    /**
     * @return array{key: string, is_active: bool}
     */
    public function restore(ShopSettings $settings, string $laborColumnKey): array
    {
        return $this->setActive($settings, $laborColumnKey, true);
    }

    /**
     * @return array{deleted: true, key: string}
     */
    public function delete(ShopSettings $settings, string $laborColumnKey): array
    {
        if (! str_starts_with($laborColumnKey, 'custom_')) {
            throw ValidationException::withMessages([
                'billing_class' => 'Built-in labor columns cannot be deleted.',
            ]);
        }

        if (CustomerBillingClassIdentity::classKeyForLaborColumn($laborColumnKey) !== null) {
            throw ValidationException::withMessages([
                'billing_class' => 'Built-in labor columns cannot be deleted.',
            ]);
        }

        return DB::transaction(function () use ($settings, $laborColumnKey): array {
            $locked = ShopSettings::query()->lockForUpdate()->first() ?? $settings;
            ShopSettings::bindCurrent($locked);

            $classes = collect(is_array($locked->custom_labor_billing_classes) ? $locked->custom_labor_billing_classes : [])
                ->filter(fn ($row): bool => is_array($row))
                ->values()
                ->all();

            $index = collect($classes)->search(fn (array $row): bool => ($row['key'] ?? '') === $laborColumnKey);
            if ($index === false) {
                throw ValidationException::withMessages([
                    'billing_class' => 'Labor column not found.',
                ]);
            }

            $refs = $this->references($laborColumnKey);
            if ($refs !== []) {
                throw ValidationException::withMessages([
                    'billing_class' => 'This labor column is referenced by existing records. Archive it instead of deleting.',
                    'references' => collect($refs)->pluck('label')->all(),
                ]);
            }

            // Linked customer classes must be handled separately — do not cascade.
            $linkedCustomer = collect($locked->storedCustomerTypeRows())
                ->first(fn (array $row): bool => ($row['labor_column_key'] ?? null) === $laborColumnKey);
            if ($linkedCustomer !== null) {
                throw ValidationException::withMessages([
                    'billing_class' => 'A customer Billing Class still links to this labor column. Archive or unlink that class first.',
                ]);
            }

            unset($classes[$index]);
            $locked->update([
                'custom_labor_billing_classes' => array_values($classes),
                'labor_pricing_profile_version' => max(1, (int) ($locked->labor_pricing_profile_version ?? 0) + 1),
            ]);

            LaborPolicy::query()->where('billing_posture', $laborColumnKey)->delete();

            $minimums = is_array($locked->labor_billing_minimums) ? $locked->labor_billing_minimums : [];
            unset($minimums[$laborColumnKey]);
            $locked->update(['labor_billing_minimums' => $minimums]);

            ShopSettings::forgetCurrent();

            return ['deleted' => true, 'key' => $laborColumnKey];
        });
    }

    /**
     * @return array{key: string, is_active: bool}
     */
    private function setActive(ShopSettings $settings, string $laborColumnKey, bool $active): array
    {
        if (! str_starts_with($laborColumnKey, 'custom_')) {
            throw ValidationException::withMessages([
                'billing_class' => 'Built-in labor columns cannot be archived.',
            ]);
        }

        return DB::transaction(function () use ($settings, $laborColumnKey, $active): array {
            $locked = ShopSettings::query()->lockForUpdate()->first() ?? $settings;
            ShopSettings::bindCurrent($locked);

            $classes = collect(is_array($locked->custom_labor_billing_classes) ? $locked->custom_labor_billing_classes : [])
                ->filter(fn ($row): bool => is_array($row))
                ->values()
                ->all();

            $index = collect($classes)->search(fn (array $row): bool => ($row['key'] ?? '') === $laborColumnKey);
            if ($index === false) {
                throw ValidationException::withMessages([
                    'billing_class' => 'Labor column not found.',
                ]);
            }

            $classes[$index]['is_active'] = $active;
            $locked->update([
                'custom_labor_billing_classes' => $classes,
                'labor_pricing_profile_version' => max(1, (int) ($locked->labor_pricing_profile_version ?? 0) + 1),
            ]);
            ShopSettings::forgetCurrent();

            return ['key' => $laborColumnKey, 'is_active' => $active];
        });
    }
}
