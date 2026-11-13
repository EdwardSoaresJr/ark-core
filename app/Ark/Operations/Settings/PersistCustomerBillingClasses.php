<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\EstimatePricing\CommercialPricingProfile;
use App\Ark\Operations\EstimatePricing\CustomerBillingClassIdentity;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single persistence authority for customer Billing Classes (#180).
 * Forward-only: stamps living commercial profiles before mutating shop settings.
 */
final class PersistCustomerBillingClasses
{
    /**
     * @param  list<array<string, mixed>>  $customerTypes
     * @return list<array<string, mixed>>
     */
    public function replaceAll(ShopSettings $settings, array $customerTypes): array
    {
        return DB::transaction(function () use ($settings, $customerTypes): array {
            $locked = $this->lockSettings($settings);
            $this->stampLivingCommercial($locked);

            $normalized = $this->normalizeIncoming($customerTypes, $locked);
            $locked->update([
                'customer_types' => $normalized,
                'commercial_pricing_profile_version' => max(1, (int) ($locked->commercial_pricing_profile_version ?? 0) + 1),
            ]);
            ShopSettings::forgetCurrent();

            return $normalized;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function upsertOne(ShopSettings $settings, string $classKey, array $attributes): array
    {
        return DB::transaction(function () use ($settings, $classKey, $attributes): array {
            $locked = $this->lockSettings($settings);
            $this->stampLivingCommercial($locked);

            $rows = $locked->storedCustomerTypeRows();
            $index = collect($rows)->search(fn (array $row): bool => ($row['key'] ?? '') === $classKey);
            if ($index === false) {
                throw ValidationException::withMessages([
                    'key' => 'Billing class not found.',
                ]);
            }

            $rows[$index] = array_merge($rows[$index], $attributes, [
                'key' => $classKey,
                'labor_column_key' => $rows[$index]['labor_column_key'] ?? null,
            ]);

            $normalized = $this->normalizeIncoming($rows, $locked);
            $locked->update([
                'customer_types' => $normalized,
                'commercial_pricing_profile_version' => max(1, (int) ($locked->commercial_pricing_profile_version ?? 0) + 1),
            ]);
            ShopSettings::forgetCurrent();

            return collect($normalized)->firstWhere('key', $classKey)
                ?? throw ValidationException::withMessages(['key' => 'Billing class not found after save.']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function create(ShopSettings $settings, array $attributes): array
    {
        return DB::transaction(function () use ($settings, $attributes): array {
            $locked = $this->lockSettings($settings);
            $this->stampLivingCommercial($locked);

            $rows = $locked->storedCustomerTypeRows();
            if (count($rows) >= 20) {
                throw ValidationException::withMessages([
                    'name' => 'This shop already has the maximum number of billing classes.',
                ]);
            }

            $name = trim((string) ($attributes['name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages([
                    'name' => 'Enter a billing class name.',
                ]);
            }

            if (collect($rows)->contains(fn (array $row): bool => strcasecmp($row['name'], $name) === 0)) {
                throw ValidationException::withMessages([
                    'name' => 'A billing class with that name already exists.',
                ]);
            }

            $reserved = collect($rows)->pluck('key')->filter()->values()->all();
            $key = CustomerBillingClassIdentity::keyForRow([
                'name' => $name,
                'key' => $attributes['key'] ?? null,
            ], $reserved);

            // New customer classes are customer-only unless an explicit built-in labor link applies.
            $rows[] = array_merge([
                'shop_fees_enabled' => true,
                'shop_fee_rate_override' => null,
                'discount_type' => 'none',
                'discount_amount' => null,
                'default_parts_matrix_key' => null,
                'document_show_part_number' => false,
                'document_show_part_brand' => false,
                'document_show_vendor' => false,
                'pill_color' => 'slate',
                'is_active' => true,
            ], $attributes, [
                'key' => $key,
                'name' => $name,
                'is_active' => true,
            ]);
            unset($rows[array_key_last($rows)]['labor_column_key']);

            $normalized = $this->normalizeIncoming($rows, $locked);
            $locked->update([
                'customer_types' => $normalized,
                'commercial_pricing_profile_version' => max(1, (int) ($locked->commercial_pricing_profile_version ?? 0) + 1),
            ]);
            ShopSettings::forgetCurrent();

            return collect($normalized)->firstWhere('key', $key)
                ?? throw ValidationException::withMessages(['name' => 'Unable to create billing class.']);
        });
    }

    /**
     * @return array{
     *     classes: list<array<string, mixed>>,
     *     parts_matrices: list<array{key: string, name: string}>,
     *     pill_colors: list<array{key: string, label: string}>,
     *     postures: list<array{value: string, label: string}>
     * }
     */
    public function catalog(ShopSettings $settings): array
    {
        $settings = ShopSettings::current();

        $classes = collect($settings->storedCustomerTypeRows())
            ->map(function (array $row): array {
                $row['is_active'] = (bool) ($row['is_active'] ?? true);
                $row['is_labor_linked'] = CustomerBillingClassLifecycle::isLaborLinked($row);
                $row['is_customer_only'] = ! $row['is_labor_linked'];

                return $row;
            })
            ->values()
            ->all();

        $laborOnlyColumns = collect($settings->allCustomLaborBillingClasses())
            ->filter(function (array $column) use ($classes): bool {
                return ! collect($classes)->contains(
                    fn (array $row): bool => ($row['labor_column_key'] ?? null) === $column['key'],
                );
            })
            ->values()
            ->all();

        return [
            'classes' => $classes,
            // Manage modal: customer-only + archived customer classes (labor-linked actives edit via Class Settings gear).
            'manage_classes' => collect($classes)
                ->filter(fn (array $row): bool => $row['is_customer_only'] || ! $row['is_active'])
                ->values()
                ->all(),
            'labor_only_columns' => $laborOnlyColumns,
            'parts_matrices' => collect($settings->partsMatrices())
                ->map(fn (array $matrix): array => [
                    'key' => $matrix['key'],
                    'name' => $matrix['name'],
                ])
                ->values()
                ->all(),
            'pill_colors' => BillingClassPillColor::options(),
            'postures' => collect(\App\Ark\Operations\RepairOrders\ConcernBillingPosture::advisorSelectableCases())
                ->map(fn ($posture): array => [
                    'value' => $posture->value,
                    'label' => $posture->label(),
                ])
                ->values()
                ->all(),
            'commercial_pricing_profile_version' => (int) ($settings->commercial_pricing_profile_version ?? 1),
            'routes' => [
                'archive' => route('operations.settings.shop.customer-types.item.archive', ['classKey' => '__KEY__']),
                'restore' => route('operations.settings.shop.customer-types.item.restore', ['classKey' => '__KEY__']),
                'destroy' => route('operations.settings.shop.customer-types.item.destroy', ['classKey' => '__KEY__']),
                'labor_column_archive' => route('operations.settings.shop.labor-matrix.billing-classes.archive'),
                'labor_column_restore' => route('operations.settings.shop.labor-matrix.billing-classes.restore'),
                'labor_column_destroy' => route('operations.settings.shop.labor-matrix.billing-classes.destroy'),
            ],
        ];
    }

    private function lockSettings(ShopSettings $settings): ShopSettings
    {
        ShopSettings::forgetCurrent();
        $locked = ShopSettings::query()->lockForUpdate()->first() ?? $settings;
        ShopSettings::bindCurrent($locked);

        return $locked;
    }

    private function stampLivingCommercial(ShopSettings $settings): void
    {
        $profile = CommercialPricingProfile::fromShopSettings($settings)->toArray();

        RepairOrder::query()
            ->where('status', '!=', RepairOrderStatus::Closed->value)
            ->whereNull('commercial_pricing_profile')
            ->orderBy('id')
            ->chunkById(100, function ($repairOrders) use ($profile): void {
                foreach ($repairOrders as $repairOrder) {
                    $repairOrder->forceFill(['commercial_pricing_profile' => $profile])->saveQuietly();
                }
            });

        RepairOrder::query()
            ->where('status', '!=', RepairOrderStatus::Closed->value)
            ->whereNotNull('commercial_pricing_profile')
            ->orderBy('id')
            ->chunkById(100, function ($repairOrders) use ($settings): void {
                foreach ($repairOrders as $repairOrder) {
                    $stored = is_array($repairOrder->commercial_pricing_profile)
                        ? $repairOrder->commercial_pricing_profile
                        : null;
                    $profile = CommercialPricingProfile::tryFromStored($stored);
                    if ($profile === null || $profile->partsMatrices !== []) {
                        continue;
                    }

                    $repairOrder->forceFill([
                        'commercial_pricing_profile' => $profile->withPartsMatricesFrom($settings)->toArray(),
                    ])->saveQuietly();
                }
            });
    }

    /**
     * @param  list<array<string, mixed>>  $customerTypes
     * @return list<array<string, mixed>>
     */
    private function normalizeIncoming(array $customerTypes, ShopSettings $settings): array
    {
        return $settings->normalizeCustomerTypesInput($customerTypes, $settings->partsMatrices());
    }
}
