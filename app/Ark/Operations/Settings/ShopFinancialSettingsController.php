<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\EstimatePricing\LaborBillingMinimums;
use App\Ark\Operations\EstimatePricing\LaborPolicyResolver;
use App\Ark\Operations\EstimatePricing\LaborRateType;
use App\Ark\Operations\EstimatePricing\LaborWorkTypeMatrix;
use App\Ark\Operations\EstimatePricing\OperationClass;
use App\Ark\Operations\EstimatePricing\SyncStandardCustomerPayLaborRatesAction;
use App\Ark\Operations\EstimatePricing\UpsertLaborPolicyAction;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\ManualPaymentMethods;
use App\Ark\Operations\Parts\CustomerPartPresentationProfile;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\Settings\Concerns\InteractsWithShopSettingsPersistence;
use App\Ark\Operations\Settings\Concerns\NormalizesShopPartsMatrices;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShopFinancialSettingsController
{
    use Concerns\InteractsWithShopSettingsPersistence, Concerns\NormalizesShopPartsMatrices;

    public function __construct(
        private readonly EstimateDocumentService $estimateDocumentService,
        private readonly EstimateTotalsCalculator $estimateTotalsCalculator,
        private readonly SyncStandardCustomerPayLaborRatesAction $syncStandardCustomerPayLaborRates,
        private readonly LaborPolicyResolver $laborPolicyResolver,
        private readonly UpsertLaborPolicyAction $upsertLaborPolicy,
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
        $workTypeKeys = collect(LaborWorkTypeMatrix::workTypes())->pluck('key')->all();
        $columnValues = collect(LaborWorkTypeMatrix::primaryBillingColumns())->pluck('value')->all();
        $minimumPostures = collect(LaborWorkTypeMatrix::minimumConfigurablePostures())
            ->map(fn (ConcernBillingPosture $posture): string => $posture->value)
            ->all();

        $data = $request->validate([
            'standard_hourly_rate' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'matrix' => ['nullable', 'array'],
            'matrix.*.*.mode' => ['nullable', 'string', Rule::in(['standard', 'custom', 'zero'])],
            'matrix.*.*.rate' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'billing_minimums' => ['nullable', 'array'],
            'billing_minimums.*.mode' => ['nullable', 'string', Rule::in(['none', 'per_line', 'per_ro'])],
            'billing_minimums.*.hours' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'billing_minimums.*.shortfall_rate_source' => ['nullable', 'string', Rule::in(['standard_mechanical', 'custom'])],
            'billing_minimums.*.shortfall_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $previousStandardCents = (int) $settings->default_labor_rate_cents;
        $standardRateCents = (int) round(((float) $data['standard_hourly_rate']) * 100);
        $standardRateDisplay = number_format($standardRateCents / 100, 2, '.', '');
        $effectiveFrom = Carbon::now()->startOfDay();

        $defaultCategoryKey = $settings->defaultLaborCategory()['key'];
        $categoriesInput = collect($settings->laborCategories())
            ->map(function (array $category) use ($defaultCategoryKey, $standardRateDisplay): array {
                return [
                    'key' => $category['key'],
                    'name' => $category['name'],
                    'rate' => $category['key'] === $defaultCategoryKey
                        ? $standardRateDisplay
                        : number_format($category['rate_cents'] / 100, 2, '.', ''),
                    'minimum_hours' => $category['minimum_hours'],
                    'rounding_rule' => $category['rounding_rule'],
                    'allows_modifiers' => $category['allows_modifiers'] ? '1' : '0',
                    'is_active' => ($category['is_active'] ?? true) ? '1' : '0',
                ];
            })
            ->all();

        $laborCategories = $settings->normalizeLaborCategories($categoriesInput, $defaultCategoryKey);
        $billingMinimums = LaborBillingMinimums::normalizeInput($data['billing_minimums'] ?? [], $settings);
        $minimumsModel = LaborBillingMinimums::fromStored($billingMinimums);
        // Keep null when every class is "No minimum" so unchanged shops stay clean.
        $storedMinimums = $minimumsModel->enablesAnyMinimum() ? $billingMinimums : null;
        $matrixInput = $data['matrix'] ?? [];

        $committed = DB::transaction(function () use (
            $settings,
            $laborCategories,
            $standardRateCents,
            $previousStandardCents,
            $effectiveFrom,
            $storedMinimums,
            $minimumsModel,
            $matrixInput,
            $workTypeKeys,
            $columnValues,
        ): array {
            $profileVersion = (int) ($settings->labor_pricing_profile_version ?? 0);

            if ($minimumsModel->enablesAnyMinimum() && $profileVersion < 1) {
                $profileVersion = 1;
            }

            $settings->update([
                'default_labor_rate_cents' => $standardRateCents,
                'labor_categories' => $laborCategories,
                'labor_billing_minimums' => $storedMinimums,
                'labor_pricing_profile_version' => $profileVersion,
            ]);

            $syncResult = $this->syncStandardCustomerPayLaborRates->execute(
                $previousStandardCents,
                $standardRateCents,
                $effectiveFrom,
                'Standard hourly rate',
            );

            $this->applyWorkTypeMatrixRates(
                $matrixInput,
                $workTypeKeys,
                $columnValues,
                $standardRateCents,
                $effectiveFrom,
            );

            $resolved = $this->laborPolicyResolver->resolve(
                ConcernBillingPosture::CustomerPay,
                'general_repair',
            );

            if ($resolved->hourlyRateCents !== $standardRateCents
                && $resolved->rateType !== LaborRateType::Standard
                && ! in_array('general_repair', $syncResult['preserved_class_keys'], true)
            ) {
                throw ValidationException::withMessages([
                    'standard_hourly_rate' => 'Retail Mechanical rate did not match the standard hourly rate.',
                ]);
            }

            if ($resolved->rateType === LaborRateType::Standard
                && $resolved->hourlyRateCents !== $standardRateCents
            ) {
                throw ValidationException::withMessages([
                    'standard_hourly_rate' => 'Retail Mechanical rate did not match the standard hourly rate.',
                ]);
            }

            return [
                'sync' => $syncResult,
                'resolved_cents' => $resolved->hourlyRateCents,
                'profile_version' => $profileVersion,
            ];
        });

        $this->syncOpenEstimateDocuments();

        $status = 'Labor settings saved. New Retail estimates use $'
            .number_format($committed['resolved_cents'] / 100, 2)
            .'/hr';
        if ($committed['profile_version'] >= 1) {
            $status .= ' Billing-class minimums apply only to repair orders created after activation.';
        }

        return $this->redirectWithStatus($status);
    }

    /**
     * @param  array<string, array<string, array{mode?: string, rate?: mixed}>>  $matrixInput
     * @param  list<string>  $workTypeKeys
     * @param  list<string>  $columnValues
     */
    private function applyWorkTypeMatrixRates(
        array $matrixInput,
        array $workTypeKeys,
        array $columnValues,
        int $standardRateCents,
        Carbon $effectiveFrom,
    ): void {
        if ($matrixInput === []) {
            return;
        }

        $workTypes = collect(LaborWorkTypeMatrix::workTypes())
            ->keyBy('key');

        $written = [];

        foreach ($workTypeKeys as $workTypeKey) {
            $definition = $workTypes->get($workTypeKey);

            if ($definition === null || ($definition['shares_class_with'] ?? null) !== null) {
                // Shared-class rows (e.g. Programming → diagnostics) follow the source row.
                continue;
            }

            $operationClass = OperationClass::query()
                ->where('key', $definition['operation_class_key'])
                ->first();

            if ($operationClass === null) {
                continue;
            }

            $rowInput = $matrixInput[$workTypeKey] ?? [];

            if (! is_array($rowInput)) {
                continue;
            }

            foreach ($columnValues as $postureValue) {
                $cell = $rowInput[$postureValue] ?? null;

                if (! is_array($cell) || ! filled($cell['mode'] ?? null)) {
                    continue;
                }

                $writeKey = $definition['operation_class_key'].'|'.$postureValue;

                if (isset($written[$writeKey])) {
                    continue;
                }

                $mode = (string) $cell['mode'];
                $posture = ConcernBillingPosture::from($postureValue);
                $rateType = match ($mode) {
                    'standard' => LaborRateType::Standard,
                    'zero' => LaborRateType::Zero,
                    default => in_array($posture, [ConcernBillingPosture::WarrantyOther, ConcernBillingPosture::RepairPal], true)
                        ? LaborRateType::Contract
                        : LaborRateType::Hourly,
                };

                $hourlyRate = match ($mode) {
                    'standard' => $standardRateCents / 100,
                    'zero' => 0.0,
                    default => (float) ($cell['rate'] ?? 0),
                };

                $this->upsertLaborPolicy->execute(
                    $posture,
                    $operationClass,
                    $hourlyRate,
                    $effectiveFrom,
                    'Labor rate matrix',
                    $rateType,
                );

                $written[$writeKey] = true;
            }
        }
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
            'default_deposit_diagnostic_labor_category_keys' => $diagnosticKeys,
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
            'customer_types.*.pill_color' => ['nullable', 'string', Rule::in(\App\Ark\Operations\Settings\BillingClassPillColor::keys())],
            'customer_types.*.concern_billing_posture' => ['nullable', 'string', Rule::in(array_map(
                fn (ConcernBillingPosture $posture): string => $posture->value,
                ConcernBillingPosture::advisorSelectableCases(),
            ))],
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

    public function updateManualPaymentMethods(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'methods' => ['nullable', 'array', 'max:8'],
            'methods.*.key' => ['nullable', 'string', 'max:32'],
            'methods.*.label' => ['nullable', 'string', 'max:24'],
        ]);

        ShopSettings::current()->update([
            'manual_payment_methods' => ManualPaymentMethods::normalize($data['methods'] ?? []),
        ]);

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'payments'])
            ->with('status', 'Payment methods saved.');
    }
}
