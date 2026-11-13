<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\EstimatePricing\LaborBillingMinimumMode;
use App\Ark\Operations\EstimatePricing\LaborBillingMinimums;
use App\Ark\Operations\EstimatePricing\LaborPolicyResolver;
use App\Ark\Operations\EstimatePricing\LaborRateType;
use App\Ark\Operations\EstimatePricing\LaborWorkTypeMatrix;
use App\Ark\Operations\EstimatePricing\OperationClass;
use App\Ark\Operations\EstimatePricing\CommercialPricingProfile;
use App\Ark\Operations\EstimatePricing\ShopFeeProfile;
use App\Ark\Operations\EstimatePricing\ShopTaxCatalog;
use App\Ark\Operations\EstimatePricing\TaxProfile;
use App\Ark\Operations\EstimatePricing\SyncStandardCustomerPayLaborRatesAction;
use App\Ark\Operations\EstimatePricing\UpsertLaborPolicyAction;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\ManualPaymentMethods;
use App\Ark\Operations\Parts\CustomerPartPresentationProfile;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\Concerns\InteractsWithShopSettingsPersistence;
use App\Ark\Operations\Settings\Concerns\NormalizesShopPartsMatrices;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        private readonly PersistCustomerBillingClasses $persistCustomerBillingClasses,
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
            'billing_minimums.*.mode' => ['nullable', 'string', Rule::in(array_column(LaborBillingMinimumMode::cases(), 'value'))],
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

            // #194 D2: no Standard cascade. Legacy form still updates Mechanical × Retail explicitly.
            $generalRepair = OperationClass::query()->where('key', 'general_repair')->first();
            if ($generalRepair !== null) {
                $this->upsertLaborPolicy->execute(
                    ConcernBillingPosture::CustomerPay,
                    $generalRepair,
                    $standardRateCents / 100,
                    $effectiveFrom,
                    'Legacy labor form Mechanical × Retail',
                    LaborRateType::Hourly,
                );
            }

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

            return [
                'sync' => [
                    'updated_class_keys' => [],
                    'preserved_class_keys' => [],
                    'previous_standard_cents' => $previousStandardCents,
                    'next_standard_cents' => $standardRateCents,
                ],
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
            'tax_document_presentation' => ['nullable', 'string', Rule::in([
                ShopTaxCatalog::PRESENTATION_COMBINED,
                ShopTaxCatalog::PRESENTATION_ITEMIZED,
            ])],
            'tax_profiles' => ['nullable', 'array'],
            // Legacy single-rate payload (kept so existing tests / integrations keep working).
            'tax_label' => ['nullable', 'string', 'max:64'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'taxable_labor' => ['nullable', 'boolean'],
            'taxable_parts' => ['nullable', 'boolean'],
            'taxable_shop_fees' => ['nullable', 'boolean'],
        ]);

        $usingProfilesPayload = isset($data['tax_profiles']) && is_array($data['tax_profiles']) && $data['tax_profiles'] !== [];

        if (! $usingProfilesPayload && ! filled($data['tax_label'] ?? null)) {
            throw ValidationException::withMessages([
                'tax_label' => 'Enter a tax display name.',
            ]);
        }

        if (! $usingProfilesPayload && ! array_key_exists('default_tax_rate', $data)) {
            throw ValidationException::withMessages([
                'default_tax_rate' => 'Enter a sales tax rate.',
            ]);
        }

        DB::transaction(function () use ($data, $usingProfilesPayload): void {
            ShopSettings::forgetCurrent();
            $settings = ShopSettings::query()->lockForUpdate()->first() ?? ShopSettings::current();
            ShopSettings::bindCurrent($settings);

            // Freeze living ROs that still lack a tax stamp under the *current* rules
            // before this save can change rates / taxable bases.
            $this->stampLivingRepairOrdersWithCurrentTaxProfile($settings);

            $profiles = $usingProfilesPayload
                ? ShopTaxCatalog::normalizeProfiles($data['tax_profiles'])
                : ShopTaxCatalog::profilesFromLegacyRequest($data, $settings);

            $legacy = ShopTaxCatalog::projectLegacyColumns($profiles);
            $presentation = (string) ($data['tax_document_presentation']
                ?? $settings->tax_document_presentation
                ?? ShopTaxCatalog::PRESENTATION_COMBINED);

            if (! in_array($presentation, [
                ShopTaxCatalog::PRESENTATION_COMBINED,
                ShopTaxCatalog::PRESENTATION_ITEMIZED,
            ], true)) {
                $presentation = ShopTaxCatalog::PRESENTATION_COMBINED;
            }

            $settings->update([
                'tax_enabled' => (bool) ($data['tax_enabled'] ?? false),
                'tax_profiles' => $profiles,
                'tax_document_presentation' => $presentation,
                'tax_label' => $legacy['tax_label'],
                'default_tax_rate' => $legacy['default_tax_rate'],
                'taxable_labor' => $legacy['taxable_labor'],
                'taxable_parts' => $legacy['taxable_parts'],
                'taxable_shop_fees' => $legacy['taxable_shop_fees'],
            ]);
            ShopSettings::forgetCurrent();
        });

        // Forward-only: do not recalculate living ROs or rewrite open estimate snapshots.
        // Presentation preference is shop-wide for new documents; issued/archived docs stay frozen.
        return $this->redirectToTaxSettings('Tax settings saved.');
    }

    public function storeTaxProfile(Request $request): RedirectResponse
    {
        $selectedKey = null;

        DB::transaction(function () use (&$selectedKey): void {
            ShopSettings::forgetCurrent();
            $settings = ShopSettings::query()->lockForUpdate()->first() ?? ShopSettings::current();
            ShopSettings::bindCurrent($settings);

            $this->stampLivingRepairOrdersWithCurrentTaxProfile($settings);

            $profiles = ShopTaxCatalog::profilesFromShopSettings($settings);
            if (count($profiles) >= ShopTaxCatalog::MAX_PROFILES) {
                throw ValidationException::withMessages([
                    'tax_profiles' => 'Keep tax profiles to '.ShopTaxCatalog::MAX_PROFILES.' or fewer.',
                ]);
            }

            $selectedKey = 'profile-'.Str::lower(Str::random(8));
            $profiles[] = [
                'key' => $selectedKey,
                'name' => 'New tax profile',
                'is_default' => false,
                'is_active' => true,
                'state' => null,
                'counties' => [],
                'cities' => [],
                'zip_codes' => [],
                'service_area_description' => null,
                'reporting_code' => null,
                'taxable_labor' => false,
                'taxable_parts' => true,
                'taxable_shop_fees' => false,
                'components' => [[
                    'id' => (string) Str::uuid(),
                    'name' => 'Sales tax',
                    'rate' => '0.000',
                    'jurisdiction_type' => 'other',
                    'reporting_code' => null,
                    'sort_order' => 1,
                    'is_active' => true,
                ]],
            ];

            $profiles = ShopTaxCatalog::normalizeProfiles($profiles);
            $legacy = ShopTaxCatalog::projectLegacyColumns($profiles);

            $settings->update([
                'tax_profiles' => $profiles,
                'tax_label' => $legacy['tax_label'],
                'default_tax_rate' => $legacy['default_tax_rate'],
                'taxable_labor' => $legacy['taxable_labor'],
                'taxable_parts' => $legacy['taxable_parts'],
                'taxable_shop_fees' => $legacy['taxable_shop_fees'],
            ]);
            ShopSettings::forgetCurrent();
        });

        return $this->redirectToTaxSettings('Tax profile added.', $selectedKey);
    }

    public function storeTaxComponent(Request $request, string $profileKey): RedirectResponse
    {
        DB::transaction(function () use ($profileKey): void {
            ShopSettings::forgetCurrent();
            $settings = ShopSettings::query()->lockForUpdate()->first() ?? ShopSettings::current();
            ShopSettings::bindCurrent($settings);

            $this->stampLivingRepairOrdersWithCurrentTaxProfile($settings);

            $profiles = ShopTaxCatalog::profilesFromShopSettings($settings);
            $index = collect($profiles)->search(
                fn (array $profile): bool => $profile['key'] === $profileKey,
            );

            if ($index === false) {
                throw ValidationException::withMessages([
                    'tax_profiles' => 'That tax profile was not found.',
                ]);
            }

            $components = $profiles[$index]['components'] ?? [];
            if (count($components) >= ShopTaxCatalog::MAX_COMPONENTS_PER_PROFILE) {
                throw ValidationException::withMessages([
                    'tax_profiles' => 'Keep tax components to '.ShopTaxCatalog::MAX_COMPONENTS_PER_PROFILE.' or fewer per profile.',
                ]);
            }

            $components[] = [
                'id' => (string) Str::uuid(),
                'name' => 'New component',
                'rate' => '0.000',
                'jurisdiction_type' => 'other',
                'reporting_code' => null,
                'sort_order' => count($components) + 1,
                'is_active' => true,
            ];
            $profiles[$index]['components'] = $components;
            $profiles[$index]['is_active'] = true;

            $profiles = ShopTaxCatalog::normalizeProfiles($profiles);
            $legacy = ShopTaxCatalog::projectLegacyColumns($profiles);

            $settings->update([
                'tax_profiles' => $profiles,
                'tax_label' => $legacy['tax_label'],
                'default_tax_rate' => $legacy['default_tax_rate'],
                'taxable_labor' => $legacy['taxable_labor'],
                'taxable_parts' => $legacy['taxable_parts'],
                'taxable_shop_fees' => $legacy['taxable_shop_fees'],
            ]);
            ShopSettings::forgetCurrent();
        });

        return $this->redirectToTaxSettings('Tax component added.', $profileKey);
    }

    public function updateTaxProfileActive(Request $request, string $profileKey): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($profileKey, $data): void {
            ShopSettings::forgetCurrent();
            $settings = ShopSettings::query()->lockForUpdate()->first() ?? ShopSettings::current();
            ShopSettings::bindCurrent($settings);

            $this->stampLivingRepairOrdersWithCurrentTaxProfile($settings);

            $profiles = ShopTaxCatalog::profilesFromShopSettings($settings);
            $index = collect($profiles)->search(
                fn (array $profile): bool => $profile['key'] === $profileKey,
            );

            if ($index === false) {
                throw ValidationException::withMessages([
                    'tax_profiles' => 'That tax profile was not found.',
                ]);
            }

            if ($profiles[$index]['is_default'] && ! (bool) $data['is_active']) {
                throw ValidationException::withMessages([
                    'tax_profiles' => 'The default tax profile must stay active. Choose another default first.',
                ]);
            }

            $profiles[$index]['is_active'] = (bool) $data['is_active'];
            $profiles = ShopTaxCatalog::normalizeProfiles($profiles);
            $legacy = ShopTaxCatalog::projectLegacyColumns($profiles);

            $settings->update([
                'tax_profiles' => $profiles,
                'tax_label' => $legacy['tax_label'],
                'default_tax_rate' => $legacy['default_tax_rate'],
                'taxable_labor' => $legacy['taxable_labor'],
                'taxable_parts' => $legacy['taxable_parts'],
                'taxable_shop_fees' => $legacy['taxable_shop_fees'],
            ]);
            ShopSettings::forgetCurrent();
        });

        $status = (bool) $data['is_active'] ? 'Tax profile reactivated.' : 'Tax profile deactivated.';

        return $this->redirectToTaxSettings($status, $profileKey);
    }

    private function redirectToTaxSettings(string $status, ?string $selectedProfileKey = null): RedirectResponse
    {
        $query = [
            'section' => 'financial',
            'financial-tab' => 'tax',
        ];

        if (filled($selectedProfileKey)) {
            $query['tax_profile'] = $selectedProfileKey;
        }

        return redirect()
            ->route('operations.settings.shop.edit', $query)
            ->with('status', $status);
    }

    public function updateFees(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shop_fee_enabled' => ['nullable', 'boolean'],
            'shop_fee_mode' => ['nullable', 'string', Rule::in(['percent', 'flat', 'per_concern'])],
            'shop_fee_applies_to' => ['nullable', 'string', Rule::in(['labor', 'parts', 'both'])],
            'shop_fee_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shop_fee_flat' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'shop_fee_cap' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $enabled = (bool) ($data['shop_fee_enabled'] ?? false);
        $mode = (string) ($data['shop_fee_mode'] ?? ShopSettings::current()->shopFeeMode());
        if (! in_array($mode, ['percent', 'flat', 'per_concern'], true)) {
            $mode = 'percent';
        }

        $appliesTo = (string) ($data['shop_fee_applies_to'] ?? ShopSettings::current()->shopFeeAppliesTo());
        if (! in_array($appliesTo, ['labor', 'parts', 'both'], true)) {
            $appliesTo = 'both';
        }

        if ($enabled && in_array($mode, ['flat', 'per_concern'], true) && ! filled($data['shop_fee_flat'] ?? null)) {
            throw ValidationException::withMessages([
                'shop_fee_flat' => $mode === 'per_concern'
                    ? 'Enter a flat fee per concern when using that fee type.'
                    : 'Enter a fixed dollar fee when using a flat shop fee.',
            ]);
        }

        if ($enabled && $mode === 'percent' && ! filled($data['shop_fee_rate'] ?? null)) {
            throw ValidationException::withMessages([
                'shop_fee_rate' => 'Enter a shop fee rate when using a percentage fee.',
            ]);
        }

        $settings = ShopSettings::current();

        // Stamp living ROs that still use live settings so this save cannot reprice them.
        $this->stampLivingRepairOrdersWithCurrentShopFeeProfile($settings);

        // Preserve inactive method values so switching fee types does not wipe them.
        $rate = $mode === 'percent'
            ? ($data['shop_fee_rate'] ?? 0)
            : $settings->shop_fee_rate;

        $flatCents = in_array($mode, ['flat', 'per_concern'], true)
            ? (filled($data['shop_fee_flat'] ?? null)
                ? (int) round(((float) $data['shop_fee_flat']) * 100)
                : null)
            : $settings->shop_fee_flat_cents;

        $capCents = in_array($mode, ['percent', 'per_concern'], true)
            ? (filled($data['shop_fee_cap'] ?? null)
                ? (int) round(((float) $data['shop_fee_cap']) * 100)
                : null)
            : $settings->shop_fee_cap_cents;

        $settings->update([
            'shop_fee_enabled' => $enabled,
            'shop_fee_mode' => $mode,
            'shop_fee_applies_to' => $appliesTo,
            'shop_fee_rate' => $rate,
            'shop_fee_flat_cents' => $flatCents,
            'shop_fee_cap_cents' => $capCents,
        ]);

        // Forward-only: do not recalculate living ROs or rewrite open estimate snapshots.
        // New ROs pick up the updated fee profile through the established stamp-on-create path.

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
        $data = $request->validate($this->customerTypesValidationRules());

        $this->persistCustomerBillingClasses->replaceAll(
            ShopSettings::current(),
            $data['customer_types'] ?? [],
        );

        // Forward-only: do not recalculate living ROs or rewrite open estimate snapshots.
        return $this->redirectWithStatus('Billing class settings saved.');
    }

    public function showCustomerTypes(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->persistCustomerBillingClasses->catalog(ShopSettings::current()));
    }

    public function updateCustomerTypeItem(Request $request, string $classKey): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate($this->singleCustomerTypeValidationRules());

        $saved = $this->persistCustomerBillingClasses->upsertOne(
            ShopSettings::current(),
            $classKey,
            $data,
        );

        return response()->json([
            'class' => $saved,
            'catalog' => $this->persistCustomerBillingClasses->catalog(ShopSettings::current()),
            'status' => 'Billing class saved.',
        ]);
    }

    public function storeCustomerType(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate($this->singleCustomerTypeValidationRules());

        $saved = $this->persistCustomerBillingClasses->create(ShopSettings::current(), $data);

        return response()->json([
            'class' => $saved,
            'catalog' => $this->persistCustomerBillingClasses->catalog(ShopSettings::current()),
            'status' => 'Billing class created.',
        ], 201);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function customerTypesValidationRules(): array
    {
        return [
            'customer_types' => ['nullable', 'array', 'max:20'],
            'customer_types.*.key' => ['nullable', 'string', 'max:48'],
            'customer_types.*.labor_column_key' => ['nullable', 'string', 'max:48'],
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
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function singleCustomerTypeValidationRules(): array
    {
        return [
            'key' => ['nullable', 'string', 'max:48'],
            'labor_column_key' => ['nullable', 'string', 'max:48'],
            'name' => ['required', 'string', 'max:255'],
            'default_parts_matrix_key' => ['nullable', 'string', 'max:255'],
            'shop_fees_enabled' => ['nullable', 'boolean'],
            'shop_fee_rate_override' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_type' => ['nullable', 'string', Rule::in(['none', 'labor', 'parts', 'both'])],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'document_presentation_profile' => ['nullable', Rule::enum(CustomerPartPresentationProfile::class)],
            'pill_color' => ['nullable', 'string', Rule::in(\App\Ark\Operations\Settings\BillingClassPillColor::keys())],
            'concern_billing_posture' => ['nullable', 'string', Rule::in(array_map(
                fn (ConcernBillingPosture $posture): string => $posture->value,
                ConcernBillingPosture::advisorSelectableCases(),
            ))],
        ];
    }

    public function updatePartsMatrix(Request $request, string $matrixKey): JsonResponse|RedirectResponse
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

        DB::transaction(function () use ($partsMatrices): void {
            ShopSettings::forgetCurrent();
            $settings = ShopSettings::query()->lockForUpdate()->first() ?? ShopSettings::current();
            ShopSettings::bindCurrent($settings);

            // Freeze matrix tier definitions onto living ROs before shop-wide matrix edits.
            $this->stampLivingRepairOrdersWithCurrentCommercialPricingProfile($settings);
            $this->ensureLivingCommercialProfilesIncludePartsMatrices($settings);

            // Preserve archived matrix definitions; the form only posts active matrices.
            $mergedMatrices = $this->mergeArchivedPartsMatrices($partsMatrices, $settings);

            $settings->update([
                'parts_matrix' => ShopSettings::DEFAULT_PARTS_MATRIX,
                'parts_matrices' => $mergedMatrices,
            ]);
            ShopSettings::forgetCurrent();
        });

        // Forward-only: do not rewrite open estimate snapshots on matrix edits.
        // Autosave clients must not infer success from redirects. JSON commits are explicit.
        if ($request->expectsJson()) {
            $persisted = ShopSettings::current()->fresh()->partsMatrices();

            return response()->json([
                'ok' => true,
                'committed' => true,
                'matrix_key' => $matrixKey,
                'default_parts_matrix_key' => ShopSettings::current()->defaultPartsMatrix()['key'],
                'parts_matrices' => $persisted,
                'message' => 'Parts matrix saved.',
            ]);
        }

        return redirect()
            ->route('operations.settings.shop.edit')
            ->with('status', 'Parts matrix saved.');
    }

    public function destroyPartsMatrix(Request $request, string $matrixKey): RedirectResponse
    {
        DB::transaction(function () use ($request, $matrixKey): void {
            ShopSettings::forgetCurrent();
            $settings = ShopSettings::query()->lockForUpdate()->first() ?? ShopSettings::current();
            ShopSettings::bindCurrent($settings);

            $storedMatrices = collect($settings->parts_matrices ?: ShopSettings::DEFAULT_PARTS_MATRICES)
                ->filter(fn ($matrix): bool => is_array($matrix) && filled($matrix['key'] ?? null))
                ->values()
                ->all();

            $matrix = collect($storedMatrices)->firstWhere('key', $matrixKey);
            abort_unless($matrix !== null && ($matrix['is_active'] ?? true) !== false, 404);

            $request->validate([
                'confirm_name' => ['required', 'string', Rule::in([(string) $matrix['name']])],
            ]);

            $activeCount = collect($storedMatrices)
                ->filter(fn (array $candidate): bool => ($candidate['is_active'] ?? true) !== false)
                ->count();

            if ($activeCount <= 1) {
                throw ValidationException::withMessages([
                    'parts_matrices' => 'At least one parts matrix must remain active.',
                ]);
            }

            if ($settings->defaultPartsMatrix()['key'] === $matrixKey) {
                throw ValidationException::withMessages([
                    'parts_matrices' => 'Choose a different default matrix before archiving this one.',
                ]);
            }

            // Freeze current matrix definitions onto living ROs before soft-archiving.
            $this->stampLivingRepairOrdersWithCurrentCommercialPricingProfile($settings);
            $this->ensureLivingCommercialProfilesIncludePartsMatrices($settings);

            $archivedMatrices = collect($storedMatrices)
                ->map(function (array $candidate) use ($matrixKey): array {
                    if ($candidate['key'] !== $matrixKey) {
                        return $candidate;
                    }

                    $candidate['is_active'] = false;
                    $candidate['is_default'] = false;

                    return $candidate;
                })
                ->values()
                ->all();

            $activeDefaultKey = collect($archivedMatrices)
                ->first(fn (array $candidate): bool => ($candidate['is_active'] ?? true) !== false
                    && (bool) ($candidate['is_default'] ?? false))['key']
                ?? collect($archivedMatrices)
                    ->first(fn (array $candidate): bool => ($candidate['is_active'] ?? true) !== false)['key'];

            $settings->update([
                'parts_matrix' => ShopSettings::DEFAULT_PARTS_MATRIX,
                'parts_matrices' => $this->normalizePartsMatrices($archivedMatrices, $activeDefaultKey),
                'customer_types' => $settings->customerTypesWithoutPartsMatrix($matrixKey),
                'commercial_pricing_profile_version' => max(1, (int) ($settings->commercial_pricing_profile_version ?? 0) + 1),
            ]);
            ShopSettings::forgetCurrent();
        });

        // Forward-only: do not rewrite open estimate snapshots when archiving a matrix.
        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'financial'])
            ->with('status', 'Parts matrix archived.');
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

    /**
     * Capture current fee settings onto living ROs that still lack a stamp so a subsequent
     * settings save cannot change their fee math on later recalculation.
     */
    private function stampLivingRepairOrdersWithCurrentShopFeeProfile(ShopSettings $settings): void
    {
        $profile = ShopFeeProfile::fromShopSettings($settings)->toArray();

        RepairOrder::query()
            ->where('status', '!=', RepairOrderStatus::Closed->value)
            ->whereNull('shop_fee_profile')
            ->orderBy('id')
            ->chunkById(100, function ($repairOrders) use ($profile): void {
                foreach ($repairOrders as $repairOrder) {
                    $repairOrder->forceFill(['shop_fee_profile' => $profile])->saveQuietly();
                }
            });
    }

    /**
     * Capture current tax rules onto living ROs that still lack a stamp so a subsequent
     * Tax settings save cannot change their tax math on later recalculation.
     */
    private function stampLivingRepairOrdersWithCurrentTaxProfile(ShopSettings $settings): void
    {
        $profile = TaxProfile::fromShopSettings($settings)->toArray();

        RepairOrder::query()
            ->where('status', '!=', RepairOrderStatus::Closed->value)
            ->whereNull('tax_profile')
            ->orderBy('id')
            ->chunkById(100, function ($repairOrders) use ($profile): void {
                foreach ($repairOrders as $repairOrder) {
                    $repairOrder->forceFill(['tax_profile' => $profile])->saveQuietly();
                }
            });
    }

    /**
     * Keep soft-archived matrix definitions when the active editor posts only live matrices.
     *
     * @param  list<array<string, mixed>>  $activeMatrices
     * @return list<array<string, mixed>>
     */
    private function mergeArchivedPartsMatrices(array $activeMatrices, ShopSettings $settings): array
    {
        $activeKeys = collect($activeMatrices)->pluck('key')->all();
        $archived = collect($settings->archivedPartsMatrices())
            ->reject(fn (array $matrix): bool => in_array($matrix['key'], $activeKeys, true))
            ->map(function (array $matrix): array {
                $matrix['is_active'] = false;
                $matrix['is_default'] = false;

                return $matrix;
            })
            ->values()
            ->all();

        return $this->normalizePartsMatrices([...$activeMatrices, ...$archived]);
    }

    /**
     * Capture current billing-class commercial rules onto living ROs that still lack a stamp
     * so a Billing Classes settings save cannot change discounts/fees on later recalculation.
     * Does not rewrite line amounts. Closed ROs are left null (recalc paths skip them).
     */
    private function stampLivingRepairOrdersWithCurrentCommercialPricingProfile(ShopSettings $settings): void
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
    }

    /**
     * Schema-1 commercial stamps only stored matrix keys. Before matrix tier edits, freeze the
     * then-current matrix definitions onto those living ROs without rewriting line amounts.
     */
    private function ensureLivingCommercialProfilesIncludePartsMatrices(ShopSettings $settings): void
    {
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
}
