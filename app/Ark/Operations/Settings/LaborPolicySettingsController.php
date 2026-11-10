<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\EstimatePricing\LaborPoliciesMatrixProjection;
use App\Ark\Operations\EstimatePricing\LaborPolicyResolver;
use App\Ark\Operations\EstimatePricing\LaborRateType;
use App\Ark\Operations\EstimatePricing\OperationClass;
use App\Ark\Operations\EstimatePricing\UpsertLaborPolicyAction;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LaborPolicySettingsController
{
    public function __construct(
        private readonly UpsertLaborPolicyAction $upsertLaborPolicy,
        private readonly LaborPolicyResolver $laborPolicyResolver,
    ) {}

    public function update(Request $request): RedirectResponse
    {
        $postureValues = collect(LaborPoliciesMatrixProjection::matrixPostures())
            ->map(fn (ConcernBillingPosture $posture): string => $posture->value)
            ->all();
        $classKeys = OperationClass::query()->pluck('key')->all();

        $data = $request->validate([
            'billing_posture' => ['required', 'string', Rule::in($postureValues)],
            'operation_class_key' => ['required', 'string', Rule::in($classKeys)],
            'rate_mode' => ['nullable', 'string', Rule::in(['standard', 'custom', 'zero'])],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'effective_from' => ['required', 'date'],
            'change_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $posture = ConcernBillingPosture::from($data['billing_posture']);
        $operationClass = OperationClass::query()->where('key', $data['operation_class_key'])->firstOrFail();
        $mode = (string) ($data['rate_mode'] ?? 'custom');
        $rateType = match ($mode) {
            'standard' => LaborRateType::Standard,
            'zero' => LaborRateType::Zero,
            default => in_array($posture, [ConcernBillingPosture::WarrantyOther, ConcernBillingPosture::RepairPal], true)
                ? LaborRateType::Contract
                : LaborRateType::Hourly,
        };

        $hourlyRate = match ($mode) {
            'standard' => ShopSettings::current()->defaultLaborRateCents() / 100,
            'zero' => 0.0,
            default => (float) ($data['hourly_rate'] ?? 0),
        };

        if ($mode === 'custom' && ! isset($data['hourly_rate'])) {
            throw ValidationException::withMessages([
                'hourly_rate' => 'Enter a custom hourly rate.',
            ]);
        }

        $this->upsertLaborPolicy->execute(
            $posture,
            $operationClass,
            $hourlyRate,
            Carbon::parse($data['effective_from'])->startOfDay(),
            filled($data['change_reason'] ?? null) ? trim((string) $data['change_reason']) : null,
            $rateType,
        );

        try {
            $resolved = $this->laborPolicyResolver->resolve(
                $posture,
                $operationClass->key,
                Carbon::parse($data['effective_from']),
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'hourly_rate' => 'Policy saved but could not be resolved: '.$e->getMessage(),
            ]);
        }

        return redirect()
            ->route('operations.settings.shop.edit', [
                'section' => 'financial',
                'financial-tab' => 'labor',
            ])
            ->with('status', 'Advanced billing rate saved. Effective rate: $'
                .number_format($resolved->hourlyRateCents / 100, 2)
                .'/hr.');
    }
}
