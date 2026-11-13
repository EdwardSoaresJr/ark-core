<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\EstimatePricing\CustomerBillingClassIdentity;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Archive / restore / delete for customer Billing Classes (customer_types[]).
 *
 * Does not mutate labor matrix columns. A linked labor_column_key is an association
 * only — archiving or deleting the customer class never deletes labor rates or postures.
 */
final class CustomerBillingClassLifecycle
{
    public function __construct(
        private readonly PersistCustomerBillingClasses $persist,
    ) {}

    /**
     * @return list<array{code: string, label: string, count: int}>
     */
    public function references(ShopSettings $settings, string $classKey): array
    {
        $row = collect($settings->storedCustomerTypeRows())->firstWhere('key', $classKey);
        if ($row === null) {
            return [];
        }

        $name = (string) $row['name'];
        $refs = [];

        $customerCount = Customer::query()
            ->whereRaw('LOWER(TRIM(customer_type)) = ?', [mb_strtolower(trim($name))])
            ->count();
        if ($customerCount > 0) {
            $refs[] = [
                'code' => 'customers',
                'label' => $customerCount === 1 ? '1 customer uses this billing class' : "{$customerCount} customers use this billing class",
                'count' => $customerCount,
            ];
        }

        $stampCount = 0;
        RepairOrder::query()
            ->whereNotNull('commercial_pricing_profile')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($classKey, $name, &$stampCount): void {
                foreach ($orders as $order) {
                    $profile = $order->commercial_pricing_profile;
                    if (! is_array($profile)) {
                        continue;
                    }
                    foreach ($profile['customer_types'] ?? [] as $typed) {
                        if (! is_array($typed)) {
                            continue;
                        }
                        if (($typed['key'] ?? null) === $classKey
                            || strcasecmp((string) ($typed['name'] ?? ''), $name) === 0) {
                            $stampCount++;
                            break;
                        }
                    }
                }
            });

        if ($stampCount > 0) {
            $refs[] = [
                'code' => 'commercial_stamps',
                'label' => $stampCount === 1
                    ? '1 repair order commercial stamp includes this class'
                    : "{$stampCount} repair order commercial stamps include this class",
                'count' => $stampCount,
            ];
        }

        return $refs;
    }

    /**
     * @return array<string, mixed>
     */
    public function archive(ShopSettings $settings, string $classKey): array
    {
        return $this->setActive($settings, $classKey, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function restore(ShopSettings $settings, string $classKey): array
    {
        return $this->setActive($settings, $classKey, true);
    }

    /**
     * @return array{deleted: true, key: string}
     */
    public function delete(ShopSettings $settings, string $classKey): array
    {
        return DB::transaction(function () use ($settings, $classKey): array {
            $locked = ShopSettings::query()->lockForUpdate()->first() ?? $settings;
            ShopSettings::bindCurrent($locked);

            $rows = $locked->storedCustomerTypeRows();
            $index = collect($rows)->search(fn (array $row): bool => ($row['key'] ?? '') === $classKey);
            if ($index === false) {
                throw ValidationException::withMessages([
                    'key' => 'Billing class not found.',
                ]);
            }

            $refs = $this->references($locked, $classKey);
            if ($refs !== []) {
                throw ValidationException::withMessages([
                    'key' => 'This billing class is referenced by existing records. Archive it instead of deleting.',
                    'references' => collect($refs)->pluck('label')->all(),
                ]);
            }

            // Never remove the last remaining class.
            if (count($rows) <= 1) {
                throw ValidationException::withMessages([
                    'key' => 'At least one billing class must remain.',
                ]);
            }

            unset($rows[$index]);
            $normalized = $locked->normalizeCustomerTypesInput(array_values($rows));
            $locked->update([
                'customer_types' => $normalized,
                'commercial_pricing_profile_version' => max(1, (int) ($locked->commercial_pricing_profile_version ?? 0) + 1),
            ]);
            ShopSettings::forgetCurrent();

            return ['deleted' => true, 'key' => $classKey];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function setActive(ShopSettings $settings, string $classKey, bool $active): array
    {
        $row = collect($settings->storedCustomerTypeRows())->firstWhere('key', $classKey);
        if ($row === null) {
            throw ValidationException::withMessages(['key' => 'Billing class not found.']);
        }

        // Archiving a customer class never archives its linked labor column.
        return $this->persist->upsertOne($settings, $classKey, [
            ...$row,
            'is_active' => $active,
        ]);
    }

    public static function isLaborLinked(?array $row): bool
    {
        if ($row === null) {
            return false;
        }

        return filled($row['labor_column_key'] ?? null)
            || CustomerBillingClassIdentity::laborColumnForClassKey($row['key'] ?? null) !== null;
    }
}
