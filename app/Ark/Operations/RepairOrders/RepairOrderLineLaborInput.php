<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Labor\LaborAdjustment;
use App\Ark\Operations\Labor\LaborAdjustmentReason;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RepairOrderLineLaborInput
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(Request $request): array
    {
        return [
            'labor_category_key' => ['nullable', 'string', 'max:64'],
            'labor_entered_hours' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'labor_adjustment' => ['nullable', Rule::enum(LaborAdjustment::class)],
            'labor_adjustment_factor' => ['nullable', 'numeric', 'min:1', 'max:3'],
            'labor_adjustment_reason' => ['nullable', Rule::enum(LaborAdjustmentReason::class)],
            'labor_adjustment_reason_custom' => ['nullable', 'string', 'max:255'],
            'labor_hours_overridden' => ['nullable', 'boolean'],
            'labor_rate_overridden' => ['nullable', 'boolean'],
            'reprice_labor' => ['nullable', 'boolean'],
            'labor_override_reason' => ['nullable', 'string', 'max:255'],
            'labor_rate_override_reason' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @param  array<string, mixed>  $pricingAttributes
     * @return array<string, mixed>
     */
    public static function persistenceAttributes(array $pricingAttributes): array
    {
        return [
            'labor_category_key' => $pricingAttributes['labor_category_key'] ?? null,
            'labor_category_name' => $pricingAttributes['labor_category_name'] ?? null,
            'labor_entered_hours' => $pricingAttributes['labor_entered_hours'] ?? null,
            'labor_adjustment' => $pricingAttributes['labor_adjustment'] ?? null,
            'labor_adjustment_factor' => $pricingAttributes['labor_adjustment_factor'] ?? null,
            'labor_adjustment_reason' => $pricingAttributes['labor_adjustment_reason'] ?? null,
            'labor_billed_hours' => $pricingAttributes['labor_billed_hours'] ?? null,
            'labor_hours_overridden' => $pricingAttributes['labor_hours_overridden'] ?? false,
            'labor_override_reason' => $pricingAttributes['labor_override_reason'] ?? null,
            'labor_minimum_applied' => $pricingAttributes['labor_minimum_applied'] ?? false,
            'labor_rate_cents' => $pricingAttributes['labor_rate_cents'] ?? null,
            'policy_resolved_labor_rate_cents' => $pricingAttributes['policy_resolved_labor_rate_cents'] ?? null,
            'resolved_from_posture' => $pricingAttributes['resolved_from_posture'] ?? null,
            'resolved_from_operation_class' => $pricingAttributes['resolved_from_operation_class'] ?? null,
            'labor_policy_id' => $pricingAttributes['labor_policy_id'] ?? null,
            'labor_policy_version' => $pricingAttributes['labor_policy_version'] ?? null,
            'labor_rate_override_reason' => $pricingAttributes['labor_rate_override_reason'] ?? null,
            'labor_rate_overridden_at' => $pricingAttributes['labor_rate_overridden_at'] ?? null,
            'labor_rate_overridden_by_user_id' => $pricingAttributes['labor_rate_overridden_by_user_id'] ?? null,
            'operation_id' => $pricingAttributes['operation_id'] ?? null,
        ];
    }
}
