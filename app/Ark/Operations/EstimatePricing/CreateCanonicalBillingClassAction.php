<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\Settings\PersistCustomerBillingClasses;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The one way to create a billing class (#217).
 *
 * One customer_types[] row with a stable key, always linked to a labor column
 * (built-in link, or a new custom_* column with labor policies and minimums), so a class
 * created once appears for customer assignment, concern override, the labor matrix and
 * commercial rules.
 */
final class CreateCanonicalBillingClassAction
{
    public function __construct(
        private readonly CreateLaborBillingClassAction $createLaborColumn,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  customer_types fields plus optional base_posture
     * @return array<string, mixed> the saved customer_types row (includes labor_column_key)
     */
    public function execute(ShopSettings $settings, array $attributes): array
    {
        return app(PersistCustomerBillingClasses::class)->create($settings, $attributes);
    }

    /**
     * Link target for a class: the explicit built-in labor column, an existing column the
     * caller named, or a freshly created custom_* column. Never merges by name.
     * Must run inside the settings lock of the caller.
     */
    public function ensureLaborColumn(
        ShopSettings $settings,
        string $classKey,
        string $name,
        ?string $basePosture = null,
        ?string $requestedColumn = null,
    ): string {
        $builtin = CustomerBillingClassIdentity::laborColumnForClassKey($classKey);
        if ($builtin !== null) {
            return $builtin;
        }

        $requested = trim((string) $requestedColumn);
        if ($requested !== '' && collect(LaborWorkTypeMatrix::matrixBillingColumns($settings))->contains('value', $requested)) {
            return $requested;
        }

        return $this->createLaborColumn->execute($name, $basePosture ?? 'customer_pay', $settings)['key'];
    }

    /**
     * Backfill: give every customer-only class its own linked custom labor column from default rates.
     * Classes that collide with an existing differently-keyed labor column of the same label are
     * left unlinked and reported — never merged.
     *
     * @return array{linked: list<array{class_key: string, name: string, labor_column_key: string}>, collisions: list<array{class_key: string, name: string, reason: string, existing_labor_column_key: string|null}>}
     */
    public function linkLegacyCustomerOnlyClasses(ShopSettings $settings): array
    {
        $linked = [];
        $collisions = [];

        DB::transaction(function () use ($settings, &$linked, &$collisions): void {
            $rows = $settings->storedCustomerTypeRows();
            $dirty = false;

            foreach ($rows as $index => $row) {
                if (filled($row['labor_column_key'] ?? null)) {
                    continue;
                }

                $key = (string) $row['key'];
                $name = (string) $row['name'];
                $existing = collect($settings->allCustomLaborBillingClasses())
                    ->first(fn (array $column): bool => mb_strtolower($column['label']) === mb_strtolower($name));

                if ($existing !== null) {
                    $collisions[] = [
                        'class_key' => $key,
                        'name' => $name,
                        'reason' => 'A labor-only column with the same label already exists; both preserved, not merged.',
                        'existing_labor_column_key' => $existing['key'],
                    ];

                    continue;
                }

                try {
                    $column = $this->createLaborColumn->execute($name, 'customer_pay', $settings)['key'];
                } catch (ValidationException $e) {
                    $collisions[] = [
                        'class_key' => $key,
                        'name' => $name,
                        'reason' => collect($e->errors())->flatten()->implode(' '),
                        'existing_labor_column_key' => null,
                    ];

                    continue;
                }

                $rows[$index]['labor_column_key'] = $column;
                $linked[] = ['class_key' => $key, 'name' => $name, 'labor_column_key' => $column];
                $dirty = true;
            }

            if ($dirty) {
                $settings->update(['customer_types' => $rows]);
                ShopSettings::forgetCurrent();
            }
        });

        foreach ($collisions as $collision) {
            Log::warning('billing_class_collision', $collision);
        }

        return ['linked' => $linked, 'collisions' => $collisions];
    }
}
