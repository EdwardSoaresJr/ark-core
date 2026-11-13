<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\EstimatePricing\CreateLaborBillingClassAction;
use App\Ark\Operations\EstimatePricing\CreateLaborWorkTypeAction;
use App\Ark\Operations\EstimatePricing\LaborBillingMinimumMode;
use App\Ark\Operations\EstimatePricing\LaborBillingMinimums;
use App\Ark\Operations\EstimatePricing\LaborPolicyResolver;
use App\Ark\Operations\EstimatePricing\LaborRateType;
use App\Ark\Operations\EstimatePricing\LaborWorkTypeMatrix;
use App\Ark\Operations\EstimatePricing\LaborWorkTypeMatrixProjection;
use App\Ark\Operations\EstimatePricing\OperationClass;
use App\Ark\Operations\EstimatePricing\UpsertLaborPolicyAction;
use App\Ark\Operations\Labor\LaborRoundingRule;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LaborMatrixSettingsController
{
    public function __construct(
        private readonly UpsertLaborPolicyAction $upsertLaborPolicy,
        private readonly CreateLaborWorkTypeAction $createLaborWorkType,
        private readonly CreateLaborBillingClassAction $createLaborBillingClass,
        private readonly LaborWorkTypeMatrixProjection $projection,
        private readonly LaborPolicyResolver $laborPolicyResolver,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json($this->projection->build());
    }

    public function updateRate(Request $request): JsonResponse
    {
        $workTypeKeys = collect(LaborWorkTypeMatrix::workTypes())->pluck('key')->all();
        $columnValues = collect(LaborWorkTypeMatrix::matrixBillingColumns())->pluck('value')->all();

        $data = $request->validate([
            'work_type' => ['required', 'string', Rule::in($workTypeKeys)],
            'billing_class' => ['required', 'string', Rule::in($columnValues)],
            'mode' => ['required', 'string', Rule::in(['standard', 'custom', 'zero'])],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'expected_version' => ['nullable', 'integer', 'min:0'],
        ]);

        $settings = ShopSettings::current();
        $this->assertVersion($settings, $data['expected_version'] ?? null);

        $workType = collect(LaborWorkTypeMatrix::workTypes($settings))->firstWhere('key', $data['work_type']);
        $operationClass = OperationClass::query()->where('key', $workType['operation_class_key'])->firstOrFail();
        $mode = (string) $data['mode'];

        DB::transaction(function () use ($settings, $data, $operationClass, &$mode): void {
            $effectiveFrom = Carbon::now()->startOfDay();
            $rateCents = match ($mode) {
                'zero' => 0,
                'standard' => $settings->defaultLaborRateCents(),
                default => (int) round(((float) ($data['rate'] ?? 0)) * 100),
            };

            // Matrix UI is explicit-rate only ($0 = no charge). Do not rewrite shop-wide
            // default_labor_rate_cents or cascade Standard inheritance to other cells.
            if ($mode === 'standard') {
                $mode = $rateCents <= 0 ? 'zero' : 'custom';
                if ($mode === 'zero') {
                    $rateCents = 0;
                }
            }

            $posture = ConcernBillingPosture::tryFrom($data['billing_class']);

            if ($posture instanceof ConcernBillingPosture) {
                $rateType = match ($mode) {
                    'standard' => LaborRateType::Standard,
                    'zero' => LaborRateType::Zero,
                    default => in_array($posture, [ConcernBillingPosture::WarrantyOther, ConcernBillingPosture::RepairPal], true)
                        ? LaborRateType::Contract
                        : LaborRateType::Hourly,
                };

                $this->upsertLaborPolicy->execute(
                    $posture,
                    $operationClass,
                    $rateCents / 100,
                    $effectiveFrom,
                    'Labor rate matrix',
                    $rateType,
                );
            } else {
                // Custom billing class key — write labor_policies directly.
                $this->upsertCustomPosturePolicy(
                    $data['billing_class'],
                    $operationClass,
                    $mode,
                    $rateCents,
                    $effectiveFrom,
                );
            }

            $settings->update([
                'labor_pricing_profile_version' => max(1, (int) ($settings->labor_pricing_profile_version ?? 0) + 1),
            ]);
        });

        return response()->json([
            'status' => 'saved',
            'applies_to' => 'new_repair_orders_only',
            'matrix' => $this->projection->build(),
        ]);
    }

    public function updateMinimum(Request $request): JsonResponse
    {
        $columnValues = collect(LaborWorkTypeMatrix::matrixBillingColumns())->pluck('value')->all();

        $data = $request->validate([
            'billing_class' => ['required', 'string', Rule::in($columnValues)],
            'mode' => ['required', 'string', Rule::in(array_column(LaborBillingMinimumMode::cases(), 'value'))],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'shortfall_rate_source' => ['nullable', 'string', Rule::in([
                LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
                LaborBillingMinimums::SHORTFALL_CUSTOM,
            ])],
            'shortfall_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'expected_version' => ['nullable', 'integer', 'min:0'],
        ]);

        $settings = ShopSettings::current();
        $this->assertVersion($settings, $data['expected_version'] ?? null);

        $current = LaborBillingMinimums::fromStored(
            is_array($settings->labor_billing_minimums) ? $settings->labor_billing_minimums : null,
        )->toArray();

        $input = $current;
        $input[$data['billing_class']] = [
            'mode' => $data['mode'],
            'hours' => $data['hours'] ?? '0.00',
            'shortfall_rate_source' => $data['shortfall_rate_source'] ?? LaborBillingMinimums::SHORTFALL_STANDARD_MECHANICAL,
            'shortfall_rate' => $data['shortfall_rate'] ?? null,
        ];

        $normalized = LaborBillingMinimums::normalizeInput(
            // Normalize expects full posture map keyed input — merge single update via fake map.
            collect(LaborWorkTypeMatrix::minimumConfigurablePostures($settings))
                ->mapWithKeys(function ($posture) use ($input): array {
                    $key = LaborBillingMinimums::postureKey($posture);

                    return [$key => $input[$key] ?? ['mode' => 'none', 'hours' => '0.00']];
                })
                ->all(),
            $settings,
        );

        $minimums = LaborBillingMinimums::fromStored($normalized);
        $stored = $minimums->enablesAnyMinimum() ? $normalized : null;

        $settings->update([
            'labor_billing_minimums' => $stored,
            'labor_pricing_profile_version' => max(1, (int) ($settings->labor_pricing_profile_version ?? 0) + 1),
        ]);

        return response()->json([
            'status' => 'saved',
            'applies_to' => 'new_repair_orders_only',
            'matrix' => $this->projection->build(),
        ]);
    }

    /**
     * Round To on the matrix is work-type scoped (labor category rounding_rule).
     * There is no per-billing-class rounding in the pricing engine (#194).
     */
    public function updateWorkTypeRounding(Request $request): JsonResponse
    {
        $workTypeKeys = collect(LaborWorkTypeMatrix::workTypes())->pluck('key')->all();
        $roundingValues = array_column(LaborRoundingRule::cases(), 'value');

        $data = $request->validate([
            'work_type' => ['required', 'string', Rule::in($workTypeKeys)],
            'rounding_rule' => ['required', 'string', Rule::in($roundingValues)],
            'expected_version' => ['nullable', 'integer', 'min:0'],
        ]);

        $settings = ShopSettings::current();
        $this->assertVersion($settings, $data['expected_version'] ?? null);

        $workType = collect(LaborWorkTypeMatrix::workTypes($settings))->firstWhere('key', $data['work_type']);
        $categoryKey = (string) ($workType['labor_category_key'] ?? $data['work_type']);

        $categories = collect($settings->laborCategories())
            ->map(function (array $category) use ($categoryKey, $data): array {
                return [
                    'key' => $category['key'],
                    'name' => $category['name'],
                    'rate' => number_format($category['rate_cents'] / 100, 2, '.', ''),
                    'minimum_hours' => $category['minimum_hours'],
                    'rounding_rule' => $category['key'] === $categoryKey
                        ? $data['rounding_rule']
                        : $category['rounding_rule'],
                    // Preserve existing Adjust Labor enforcement; matrix UI does not expose it.
                    'allows_modifiers' => $category['allows_modifiers'] ? '1' : '0',
                    'is_active' => ($category['is_active'] ?? true) ? '1' : '0',
                ];
            })
            ->all();

        $settings->update([
            'labor_categories' => $settings->normalizeLaborCategories(
                $categories,
                $settings->defaultLaborCategory()['key'],
            ),
            'labor_pricing_profile_version' => max(1, (int) ($settings->labor_pricing_profile_version ?? 0) + 1),
        ]);

        return response()->json([
            'status' => 'saved',
            'applies_to' => 'new_repair_orders_only',
            'scope' => 'work_type_labor_category',
            'matrix' => $this->projection->build(),
        ]);
    }

    public function storeWorkType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
        ]);

        $created = $this->createLaborWorkType->execute($data['name'], ShopSettings::current());

        return response()->json([
            'status' => 'saved',
            'applies_to' => 'new_repair_orders_only',
            'work_type' => $created,
            'matrix' => $this->projection->build(),
        ]);
    }

    public function storeBillingClass(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:48'],
            'base_posture' => ['required', 'string', Rule::in(array_map(
                fn (ConcernBillingPosture $posture): string => $posture->value,
                ConcernBillingPosture::advisorSelectableCases(),
            ))],
        ]);

        $created = $this->createLaborBillingClass->execute(
            $data['name'],
            $data['base_posture'],
            ShopSettings::current(),
        );

        return response()->json([
            'status' => 'saved',
            'applies_to' => 'new_repair_orders_only',
            'billing_class' => $created,
            'matrix' => $this->projection->build(),
        ]);
    }

    private function assertVersion(ShopSettings $settings, mixed $expected): void
    {
        if ($expected === null) {
            return;
        }

        if ((int) $expected !== (int) ($settings->labor_pricing_profile_version ?? 0)) {
            throw ValidationException::withMessages([
                'expected_version' => 'Labor settings changed in another tab. Reload and try again.',
            ]);
        }
    }

    private function upsertCustomPosturePolicy(
        string $billingClass,
        OperationClass $operationClass,
        string $mode,
        int $rateCents,
        Carbon $effectiveFrom,
    ): void {
        $rateType = match ($mode) {
            'standard' => LaborRateType::Standard,
            'zero' => LaborRateType::Zero,
            default => LaborRateType::Hourly,
        };

        if ($rateType === LaborRateType::Standard) {
            $rateCents = ShopSettings::current()->defaultLaborRateCents();
        }

        if ($rateType === LaborRateType::Zero) {
            $rateCents = 0;
        }

        $effectiveDate = $effectiveFrom->toDateString();
        $current = \App\Ark\Operations\EstimatePricing\LaborPolicy::query()
            ->where('billing_posture', $billingClass)
            ->where('operation_class_id', $operationClass->id)
            ->whereNull('effective_until')
            ->orderByDesc('effective_from')
            ->lockForUpdate()
            ->first();

        if ($current) {
            $until = Carbon::parse($effectiveDate)->subDay()->toDateString();
            if ($current->effective_from?->toDateString() <= $until) {
                $current->update(['effective_until' => $until]);
            } else {
                $current->update(['effective_until' => $current->effective_from]);
            }
        }

        \App\Ark\Operations\EstimatePricing\LaborPolicy::query()->create([
            'billing_posture' => $billingClass,
            'operation_class_id' => $operationClass->id,
            'rate_type' => $rateType,
            'hourly_rate_cents' => $rateCents,
            'effective_from' => $effectiveDate,
            'effective_until' => null,
            'priority' => 100,
            'version' => $current ? ((int) $current->version + 1) : 1,
            'change_reason' => 'Labor rate matrix',
        ]);
    }
}
