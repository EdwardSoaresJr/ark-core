<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\EstimatePricing\LaborPolicy;
use App\Ark\Operations\EstimatePricing\OperationClass;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\WorkTemplates\WorkTemplateLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Archive / restore / delete for Labor Matrix work types (labor_categories[]).
 *
 * Archive hides the work type from new selections. Historical lines and stamps keep
 * their category keys. Delete is refused when any business record references the key.
 */
final class LaborWorkTypeLifecycle
{
    /**
     * @return list<array{code: string, label: string, count: int}>
     */
    public function references(ShopSettings $settings, string $workTypeKey): array
    {
        $refs = [];

        $lineCount = RepairOrderLine::query()
            ->where('labor_category_key', $workTypeKey)
            ->count();
        if ($lineCount > 0) {
            $refs[] = [
                'code' => 'repair_order_lines',
                'label' => $lineCount === 1
                    ? '1 repair order labor line uses this work type'
                    : "{$lineCount} repair order labor lines use this work type",
                'count' => $lineCount,
            ];
        }

        if (Schema::hasTable('work_template_lines')) {
            $templateCount = WorkTemplateLine::query()
                ->where('labor_category_key', $workTypeKey)
                ->count();
            if ($templateCount > 0) {
                $refs[] = [
                    'code' => 'work_templates',
                    'label' => $templateCount === 1
                        ? '1 work template line uses this work type'
                        : "{$templateCount} work template lines use this work type",
                    'count' => $templateCount,
                ];
            }
        }

        $depositKeys = collect($settings->default_deposit_diagnostic_labor_category_keys ?? [])
            ->map(fn ($key): string => (string) $key)
            ->all();
        if (in_array($workTypeKey, $depositKeys, true)) {
            $refs[] = [
                'code' => 'deposit_diagnostics',
                'label' => 'This work type is selected for diagnostic deposit defaults',
                'count' => 1,
            ];
        }

        return $refs;
    }

    /**
     * @return array{key: string, is_active: bool}
     */
    public function archive(ShopSettings $settings, string $workTypeKey): array
    {
        return $this->setActive($settings, $workTypeKey, false);
    }

    /**
     * @return array{key: string, is_active: bool}
     */
    public function restore(ShopSettings $settings, string $workTypeKey): array
    {
        return $this->setActive($settings, $workTypeKey, true);
    }

    /**
     * @return array{deleted: true, key: string}
     */
    public function delete(ShopSettings $settings, string $workTypeKey): array
    {
        return DB::transaction(function () use ($settings, $workTypeKey): array {
            $locked = ShopSettings::query()->lockForUpdate()->first() ?? $settings;
            ShopSettings::bindCurrent($locked);

            $categories = $locked->laborCategories();
            $category = collect($categories)->firstWhere('key', $workTypeKey);
            if ($category === null) {
                throw ValidationException::withMessages([
                    'work_type' => 'Work type not found.',
                ]);
            }

            if ($category['is_default']) {
                throw ValidationException::withMessages([
                    'work_type' => 'Choose a different default work type before deleting this one.',
                ]);
            }

            $refs = $this->references($locked, $workTypeKey);
            if ($refs !== []) {
                throw ValidationException::withMessages([
                    'work_type' => 'This work type is referenced by existing records. Archive it instead of deleting.',
                    'references' => collect($refs)->pluck('label')->all(),
                ]);
            }

            $remaining = collect($categories)
                ->reject(fn (array $row): bool => $row['key'] === $workTypeKey)
                ->values()
                ->all();

            if ($remaining === []) {
                throw ValidationException::withMessages([
                    'work_type' => 'At least one work type must remain.',
                ]);
            }

            $defaultKey = collect($remaining)->firstWhere('is_default', true)['key']
                ?? collect($remaining)->firstWhere('is_active', true)['key']
                ?? $remaining[0]['key'];

            $normalized = $locked->normalizeLaborCategories(
                collect($remaining)->map(fn (array $row): array => [
                    'key' => $row['key'],
                    'name' => $row['name'],
                    'rate' => number_format(((int) $row['rate_cents']) / 100, 2, '.', ''),
                    'minimum_hours' => $row['minimum_hours'],
                    'rounding_rule' => $row['rounding_rule'],
                    'allows_modifiers' => ($row['allows_modifiers'] ?? true) ? '1' : '0',
                    'is_active' => ($row['is_active'] ?? true) ? '1' : '0',
                ])->all(),
                $defaultKey,
            );

            $locked->update([
                'labor_categories' => $normalized,
                'labor_pricing_profile_version' => max(1, (int) ($locked->labor_pricing_profile_version ?? 0) + 1),
            ]);

            // Config-only cleanup: remove unused policies for this operation class.
            $operationClass = OperationClass::query()->where('key', $workTypeKey)->first();
            if ($operationClass !== null) {
                LaborPolicy::query()->where('operation_class_id', $operationClass->id)->delete();
            }

            ShopSettings::forgetCurrent();

            return ['deleted' => true, 'key' => $workTypeKey];
        });
    }

    /**
     * @return array{key: string, is_active: bool}
     */
    private function setActive(ShopSettings $settings, string $workTypeKey, bool $active): array
    {
        return DB::transaction(function () use ($settings, $workTypeKey, $active): array {
            $locked = ShopSettings::query()->lockForUpdate()->first() ?? $settings;
            ShopSettings::bindCurrent($locked);

            $categories = $locked->laborCategories();
            $index = collect($categories)->search(fn (array $row): bool => $row['key'] === $workTypeKey);
            if ($index === false) {
                throw ValidationException::withMessages([
                    'work_type' => 'Work type not found.',
                ]);
            }

            if (! $active && ($categories[$index]['is_default'] ?? false)) {
                throw ValidationException::withMessages([
                    'work_type' => 'Choose a different default work type before archiving this one.',
                ]);
            }

            if ($active === false) {
                $stillActive = collect($categories)
                    ->filter(fn (array $row, int $i): bool => $i !== $index && ($row['is_active'] ?? true))
                    ->count();
                if ($stillActive === 0) {
                    throw ValidationException::withMessages([
                        'work_type' => 'At least one active work type must remain.',
                    ]);
                }
            }

            $categories[$index]['is_active'] = $active;
            $defaultKey = collect($categories)->first(
                fn (array $row): bool => ($row['is_default'] ?? false) && ($row['is_active'] ?? true),
            )['key'] ?? collect($categories)->firstWhere('is_active', true)['key'] ?? $categories[0]['key'];

            $normalized = $locked->normalizeLaborCategories(
                collect($categories)->map(fn (array $row): array => [
                    'key' => $row['key'],
                    'name' => $row['name'],
                    'rate' => number_format(((int) $row['rate_cents']) / 100, 2, '.', ''),
                    'minimum_hours' => $row['minimum_hours'],
                    'rounding_rule' => $row['rounding_rule'],
                    'allows_modifiers' => ($row['allows_modifiers'] ?? true) ? '1' : '0',
                    'is_active' => ($row['is_active'] ?? true) ? '1' : '0',
                ])->all(),
                $defaultKey,
            );

            $locked->update([
                'labor_categories' => $normalized,
                'labor_pricing_profile_version' => max(1, (int) ($locked->labor_pricing_profile_version ?? 0) + 1),
            ]);
            ShopSettings::forgetCurrent();

            return ['key' => $workTypeKey, 'is_active' => $active];
        });
    }
}
