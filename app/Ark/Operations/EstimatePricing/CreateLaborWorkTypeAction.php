<?php

namespace App\Ark\Operations\EstimatePricing;

use App\Ark\Operations\OperationAuthority\Operation;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateLaborWorkTypeAction
{
    public function __construct(
        private readonly UpsertLaborPolicyAction $upsertLaborPolicy,
    ) {}

    /**
     * @return array{key: string, label: string, operation_class_key: string}
     */
    public function execute(string $name, ShopSettings $settings): array
    {
        $label = trim($name);

        if ($label === '') {
            throw ValidationException::withMessages([
                'name' => 'Enter a work type name.',
            ]);
        }

        if (mb_strlen($label) > 64) {
            throw ValidationException::withMessages([
                'name' => 'Work type names must be 64 characters or fewer.',
            ]);
        }

        $categories = $settings->laborCategories();

        if (count($categories) >= 24) {
            throw ValidationException::withMessages([
                'name' => 'This shop already has the maximum number of work types.',
            ]);
        }

        $key = Str::slug($label, '_');
        $key = $key !== '' ? $key : 'work_type_'.Str::lower(Str::random(6));
        $key = mb_substr($key, 0, 48);

        if (collect($categories)->contains(fn (array $category): bool => $category['key'] === $key
            || mb_strtolower($category['name']) === mb_strtolower($label))) {
            throw ValidationException::withMessages([
                'name' => 'A work type with that name already exists.',
            ]);
        }

        return DB::transaction(function () use ($settings, $label, $key, $categories): array {
            $sort = (int) (OperationClass::query()->max('sort_order') ?? 0) + 10;
            $operationClass = OperationClass::query()->create([
                'key' => $key,
                'name' => $label,
                'sort_order' => $sort,
            ]);

            Operation::query()->create([
                'code' => $key,
                'name' => $label,
                'operation_class_id' => $operationClass->id,
                'is_active' => true,
                'sort_order' => $sort,
            ]);

            $defaultCents = $settings->defaultLaborRateCents();
            $defaultCategoryKey = $settings->defaultLaborCategory()['key'];
            $categoriesForNormalize = collect($categories)
                ->map(fn (array $category): array => [
                    'key' => $category['key'],
                    'name' => $category['name'],
                    'rate' => number_format(((int) $category['rate_cents']) / 100, 2, '.', ''),
                    'minimum_hours' => $category['minimum_hours'],
                    'rounding_rule' => $category['rounding_rule'],
                    'allows_modifiers' => ($category['allows_modifiers'] ?? true) ? '1' : '0',
                    'is_active' => ($category['is_active'] ?? true) ? '1' : '0',
                ])
                ->all();
            $categoriesForNormalize[] = [
                'key' => $key,
                'name' => $label,
                'rate' => number_format($defaultCents / 100, 2, '.', ''),
                'minimum_hours' => '0.00',
                'rounding_rule' => 'exact',
                'allows_modifiers' => '1',
                'is_active' => '1',
            ];

            $normalized = $settings->normalizeLaborCategories(
                $categoriesForNormalize,
                $defaultCategoryKey,
            );

            $settings->update([
                'labor_categories' => $normalized,
                'labor_pricing_profile_version' => max(1, (int) ($settings->labor_pricing_profile_version ?? 0) + 1),
            ]);

            $effectiveFrom = Carbon::now()->startOfDay();

            foreach (LaborWorkTypeMatrix::matrixBillingColumns($settings) as $column) {
                $posture = $column['posture'] instanceof ConcernBillingPosture
                    ? $column['posture']
                    : ConcernBillingPosture::tryFrom((string) $column['value']);

                if (! $posture instanceof ConcernBillingPosture) {
                    // Custom billing class: seed via raw policy insert.
                    LaborPolicy::query()->create([
                        'billing_posture' => (string) $column['value'],
                        'operation_class_id' => $operationClass->id,
                        'rate_type' => LaborRateType::Standard,
                        'hourly_rate_cents' => $defaultCents,
                        'effective_from' => $effectiveFrom->toDateString(),
                        'effective_until' => null,
                        'priority' => 100,
                        'version' => 1,
                        'change_reason' => 'New work type',
                    ]);

                    continue;
                }

                $mode = match ($posture) {
                    ConcernBillingPosture::Internal, ConcernBillingPosture::Comeback => LaborRateType::Zero,
                    ConcernBillingPosture::CustomerPay => LaborRateType::Standard,
                    default => LaborRateType::Hourly,
                };

                $this->upsertLaborPolicy->execute(
                    $posture,
                    $operationClass,
                    $mode === LaborRateType::Zero ? 0.0 : $defaultCents / 100,
                    $effectiveFrom,
                    'New work type',
                    $mode,
                );
            }

            return [
                'key' => $key,
                'label' => $label,
                'operation_class_key' => $operationClass->key,
            ];
        });
    }
}
