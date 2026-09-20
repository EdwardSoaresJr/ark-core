<?php

namespace App\Ark\Operations\Settings\Concerns;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Validation\ValidationException;

trait NormalizesShopPartsMatrices
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function partsMatrixValidationRules(): array
    {
        return [
            'parts_matrices' => ['required', 'array', 'min:1', 'max:20'],
            'parts_matrices.*.key' => ['required', 'string', 'max:255'],
            'parts_matrices.*.name' => ['required', 'string', 'max:255'],
            'parts_matrices.*.is_default' => ['nullable', 'boolean'],
            'parts_matrices.*.rows' => ['required', 'array', 'min:1', 'max:30'],
            'parts_matrices.*.rows.*.min_cost' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'parts_matrices.*.rows.*.max_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'parts_matrices.*.rows.*.markup_percentage' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'parts_matrices.*.rows.*.sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrices
     * @return array<int, array<string, mixed>>
     */
    protected function normalizePartsMatrices(array $matrices, ?string $defaultMatrixKey = null): array
    {
        return collect($matrices)
            ->values()
            ->map(function (array $matrix) use ($defaultMatrixKey): array {
                return [
                    'key' => $matrix['key'],
                    'name' => $matrix['name'],
                    'is_default' => $defaultMatrixKey !== null
                        ? $matrix['key'] === $defaultMatrixKey
                        : (bool) ($matrix['is_default'] ?? false),
                    'rows' => collect($matrix['rows'])
                        ->map(fn (array $row): array => [
                            'min_cost' => number_format((float) $row['min_cost'], 2, '.', ''),
                            'max_cost' => filled($row['max_cost'] ?? null) ? number_format((float) $row['max_cost'], 2, '.', '') : null,
                            'markup_percentage' => number_format((float) $row['markup_percentage'], 2, '.', ''),
                            'margin_percentage' => null,
                            'sort_order' => (int) $row['sort_order'],
                        ])
                        ->sortBy(fn (array $row): float => (float) $row['min_cost'])
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy(fn (array $matrix): string => mb_strtolower($matrix['name']))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $customerTypes
     * @param  array<int, array<string, mixed>>  $partsMatrices
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeCustomerTypes(array $customerTypes, array $partsMatrices): array
    {
        $existingTypes = collect(ShopSettings::current()->customerTypeRows())
            ->keyBy(fn (array $row): string => mb_strtolower($row['name']));
        $matrixKeys = collect($partsMatrices)->pluck('key');
        $settings = ShopSettings::current();

        return collect($customerTypes)
            ->filter(fn (array $row): bool => filled($row['name'] ?? null))
            ->map(function (array $row) use ($existingTypes, $matrixKeys, $settings): array {
                $name = trim($row['name']);
                $existing = $existingTypes->get(mb_strtolower($name), []);
                $matrixKey = filled($row['default_parts_matrix_key'] ?? null)
                    ? $row['default_parts_matrix_key']
                    : null;

                return $settings->normalizeCustomerTypeRow([
                    'name' => $name,
                    'document_presentation_profile' => filled($row['document_presentation_profile'] ?? null)
                        ? $row['document_presentation_profile']
                        : ($existing['document_presentation_profile'] ?? null),
                    'shop_fees_enabled' => (bool) ($row['shop_fees_enabled'] ?? $existing['shop_fees_enabled'] ?? true),
                    'shop_fee_rate_override' => filled($row['shop_fee_rate_override'] ?? null)
                        ? $row['shop_fee_rate_override']
                        : ($existing['shop_fee_rate_override'] ?? null),
                    'discount_type' => $row['discount_type'] ?? $existing['discount_type'] ?? 'none',
                    'discount_amount' => filled($row['discount_amount'] ?? null)
                        ? $row['discount_amount']
                        : ($existing['discount_amount'] ?? null),
                    'default_parts_matrix_key' => $matrixKey !== null && $matrixKeys->contains($matrixKey) ? $matrixKey : null,
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrices
     */
    protected function ensureNoConflictingDefaultPartsMatrices(array $matrices): void
    {
        $defaultCount = collect($matrices)
            ->filter(fn (array $matrix): bool => (bool) ($matrix['is_default'] ?? false))
            ->count();

        if ($defaultCount > 1) {
            throw ValidationException::withMessages([
                'parts_matrices' => 'Only one parts matrix can be the default.',
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrices
     */
    protected function ensureSingleDefaultPartsMatrix(array $matrices): void
    {
        $defaultCount = collect($matrices)
            ->filter(fn (array $matrix): bool => (bool) ($matrix['is_default'] ?? false))
            ->count();

        if ($defaultCount !== 1) {
            throw ValidationException::withMessages([
                'parts_matrices' => 'Select exactly one default parts matrix.',
            ]);
        }
    }
}
