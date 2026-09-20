<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\EstimatePricing\LaborPoliciesMatrixProjection;
use App\Ark\Operations\EstimatePricing\LaborPolicyResolver;
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
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'effective_from' => ['required', 'date'],
            'change_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $posture = ConcernBillingPosture::from($data['billing_posture']);
        $operationClass = OperationClass::query()->where('key', $data['operation_class_key'])->firstOrFail();

        $this->upsertLaborPolicy->execute(
            $posture,
            $operationClass,
            (float) $data['hourly_rate'],
            Carbon::parse($data['effective_from'])->startOfDay(),
            filled($data['change_reason'] ?? null) ? trim((string) $data['change_reason']) : null,
        );

        try {
            $this->laborPolicyResolver->resolve(
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
                'financial-tab' => 'labor-policies',
            ])
            ->with('status', 'Labor policy saved.');
    }
}
