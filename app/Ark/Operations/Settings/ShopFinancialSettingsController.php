<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Parts\CustomerPartPresentationProfile;
use App\Ark\Operations\Settings\Concerns\InteractsWithShopSettingsPersistence;
use App\Ark\Operations\Settings\Concerns\NormalizesShopPartsMatrices;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShopFinancialSettingsController
{
    use Concerns\InteractsWithShopSettingsPersistence, Concerns\NormalizesShopPartsMatrices;
    public function __construct(
        private readonly EstimateDocumentService $estimateDocumentService,
        private readonly EstimateTotalsCalculator $estimateTotalsCalculator,
    ) {}

    protected function estimateDocuments(): EstimateDocumentService
    {
        return $this->estimateDocumentService;
    }

    protected function totalsCalculator(): EstimateTotalsCalculator
    {
        return $this->estimateTotalsCalculator;
    }

public function updateLabor(Request $request): RedirectResponse
    {
        $settings = ShopSettings::current();
        $allowedCategoryKeys = collect($settings->laborCategories())->pluck('key')->all();

        $data = $request->validate([
            'default_labor_rate' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'default_labor_category_key' => ['required', 'string', Rule::in($allowedCategoryKeys)],
            'labor_categories' => ['required', 'array', 'min:1', 'max:20'],
            'labor_categories.*.key' => ['required', 'string', Rule::in($allowedCategoryKeys)],
            'labor_categories.*.name' => ['required', 'string', 'max:64'],
            'labor_categories.*.rate' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'labor_categories.*.minimum_hours' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'labor_categories.*.rounding_rule' => ['required', 'string', Rule::in(['exact', 'tenth', 'quarter', 'half'])],
            'labor_categories.*.allows_modifiers' => ['nullable', 'boolean'],
        ]);

        $laborCategories = $settings->normalizeLaborCategories(
            $data['labor_categories'],
            $data['default_labor_category_key'],
            (float) $data['default_labor_rate'],
        );

        $defaultRateCents = (int) collect($laborCategories)
            ->firstWhere('is_default', true)['rate_cents'];

        $settings->update([
            'default_labor_rate_cents' => $defaultRateCents,
            'labor_categories' => $laborCategories,
        ]);

        $this->syncOpenEstimateDocuments();

        return $this->redirectWithStatus('Labor settings saved.');
    }

public function updateTax(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tax_enabled' => ['nullable', 'boolean'],
            'tax_label' => ['required', 'string', 'max:64'],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'taxable_labor' => ['nullable', 'boolean'],
            'taxable_parts' => ['nullable', 'boolean'],
            'taxable_shop_fees' => ['nullable', 'boolean'],
        ]);

        ShopSettings::current()->update([
            'tax_enabled' => (bool) ($data['tax_enabled'] ?? false),
            'tax_label' => trim($data['tax_label']) ?: 'Tax',
            'default_tax_rate' => $data['default_tax_rate'],
            'taxable_labor' => (bool) ($data['taxable_labor'] ?? false),
            'taxable_parts' => (bool) ($data['taxable_parts'] ?? false),
            'taxable_shop_fees' => (bool) ($data['taxable_shop_fees'] ?? false),
        ]);

        $this->recalculateLivingRepairOrderTotals();
        $this->syncOpenEstimateDocuments();

        return $this->redirectWithStatus('Tax settings saved.');
    }

public function updateFees(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shop_fee_enabled' => ['nullable', 'boolean'],
            'shop_fee_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shop_fee_cap' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        ShopSettings::current()->update([
            'shop_fee_enabled' => (bool) ($data['shop_fee_enabled'] ?? false),
            'shop_fee_rate' => $data['shop_fee_rate'] ?? 0,
            'shop_fee_cap_cents' => filled($data['shop_fee_cap'] ?? null) ? (int) round(((float) $data['shop_fee_cap']) * 100) : null,
        ]);

        $this->recalculateLivingRepairOrderTotals();
        $this->syncOpenEstimateDocuments();

        return $this->redirectWithStatus('Shop fee settings saved.');
    }

    public function updateDeposits(Request $request): RedirectResponse
    {
        $settings = ShopSettings::current();
        $allowedCategoryKeys = collect($settings->laborCategories())->pluck('key')->all();

        $data = $request->validate([
            'default_deposit_enabled' => ['nullable', 'boolean'],
            'default_deposit_include_parts' => ['nullable', 'boolean'],
            'default_deposit_include_diagnostics' => ['nullable', 'boolean'],
            'default_deposit_diagnostic_labor_category_keys' => ['nullable', 'array'],
            'default_deposit_diagnostic_labor_category_keys.*' => ['string', Rule::in($allowedCategoryKeys)],
        ]);

        $diagnosticKeys = $settings->normalizeDefaultDepositDiagnosticLaborCategoryKeys(
            $data['default_deposit_diagnostic_labor_category_keys'] ?? [],
        );

        if (($data['default_deposit_include_diagnostics'] ?? false) && $diagnosticKeys === []) {
            throw ValidationException::withMessages([
                'default_deposit_diagnostic_labor_category_keys' => 'Select at least one labor category when diagnostics are included.',
            ]);
        }

        $settings->update([
            'default_deposit_enabled' => (bool) ($data['default_deposit_enabled'] ?? false),
            'default_deposit_include_parts' => (bool) ($data['default_deposit_include_parts'] ?? false),
            'default_deposit_include_diagnostics' => (bool) ($data['default_deposit_include_diagnostics'] ?? false),
            'default_deposit_diagnostic_labor_category_keys' => $diagnosticKeys === []
                ? ShopSettings::DEFAULT_DEPOSIT_DIAGNOSTIC_LABOR_CATEGORY_KEYS
                : $diagnosticKeys,
        ]);

        return $this->redirectWithStatus('Deposit settings saved.');
    }

public function updateCustomerTypes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_types' => ['nullable', 'array', 'max:20'],
            'customer_types.*.name' => ['nullable', 'string', 'max:255'],
            'customer_types.*.default_parts_matrix_key' => ['nullable', 'string', 'max:255'],
            'customer_types.*.shop_fees_enabled' => ['nullable', 'boolean'],
            'customer_types.*.shop_fee_rate_override' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'customer_types.*.discount_type' => ['nullable', 'string', Rule::in(['none', 'labor', 'parts', 'both'])],
            'customer_types.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'customer_types.*.document_presentation_profile' => ['nullable', Rule::enum(CustomerPartPresentationProfile::class)],
        ]);

        $settings = ShopSettings::current();

        $settings->update([
            'customer_types' => $this->normalizeCustomerTypes($data['customer_types'] ?? [], $settings->partsMatrices()),
        ]);

        $this->recalculateLivingRepairOrderTotals();
        $this->syncOpenEstimateDocuments();

        return $this->redirectWithStatus('Billing class settings saved.');
    }

public function updatePartsMatrix(Request $request, string $matrixKey): RedirectResponse
    {
        $data = $request->validate([
            ...$this->partsMatrixValidationRules(),
            'default_parts_matrix_key' => ['nullable', 'string', 'max:255'],
        ]);

        $this->ensureNoConflictingDefaultPartsMatrices($data['parts_matrices']);

        $partsMatrices = $this->normalizePartsMatrices(
            $data['parts_matrices'],
            $data['default_parts_matrix_key'] ?? null,
        );

        $this->ensureSingleDefaultPartsMatrix($partsMatrices);

        abort_unless(collect($partsMatrices)->contains(fn (array $matrix): bool => $matrix['key'] === $matrixKey), 404);

        ShopSettings::current()->update([
            'parts_matrix' => ShopSettings::DEFAULT_PARTS_MATRIX,
            'parts_matrices' => $partsMatrices,
        ]);

        $this->syncOpenEstimateDocuments();

        return redirect()
            ->route('operations.settings.shop.edit')
            ->with('status', 'Parts matrix saved.');
    }

public function destroyPartsMatrix(Request $request, string $matrixKey): RedirectResponse
    {
        $settings = ShopSettings::current();
        $matrices = $settings->parts_matrices ?: ShopSettings::DEFAULT_PARTS_MATRICES;

        $matrix = collect($matrices)->firstWhere('key', $matrixKey);

        abort_unless($matrix !== null, 404);

        $request->validate([
            'confirm_name' => ['required', 'string', Rule::in([(string) $matrix['name']])],
        ]);

        if (count($matrices) <= 1) {
            throw ValidationException::withMessages([
                'parts_matrices' => 'At least one parts matrix must remain.',
            ]);
        }

        if ($settings->defaultPartsMatrix()['key'] === $matrixKey) {
            throw ValidationException::withMessages([
                'parts_matrices' => 'Choose a different default matrix before deleting this one.',
            ]);
        }

        $remainingMatrices = collect($matrices)
            ->reject(fn (array $matrix): bool => $matrix['key'] === $matrixKey)
            ->values()
            ->all();

        $defaultMatrixKey = collect($remainingMatrices)->firstWhere('is_default', true)['key']
            ?? $remainingMatrices[0]['key'];

        $settings->update([
            'parts_matrix' => ShopSettings::DEFAULT_PARTS_MATRIX,
            'parts_matrices' => $this->normalizePartsMatrices($remainingMatrices, $defaultMatrixKey),
            'customer_types' => $settings->customerTypesWithoutPartsMatrix($matrixKey),
        ]);

        $this->syncOpenEstimateDocuments();

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'financial'])
            ->with('status', 'Parts matrix deleted.');
    }
}
