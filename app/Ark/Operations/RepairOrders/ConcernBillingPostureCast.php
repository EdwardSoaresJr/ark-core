<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Built-in postures cast to ConcernBillingPosture.
 * Shop-defined custom_* keys are preserved in the column; accessors that need fee/parts
 * behavior resolve through the custom class base_posture.
 *
 * @implements CastsAttributes<ConcernBillingPosture, string>
 */
class ConcernBillingPostureCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ConcernBillingPosture
    {
        if (is_string($value) && $value !== '') {
            $enum = ConcernBillingPosture::tryFrom($value);
            if ($enum instanceof ConcernBillingPosture) {
                return ConcernBillingPosture::fromStored($value);
            }

            $custom = ShopSettings::current()->customLaborBillingClass($value);
            if ($custom !== null) {
                return ConcernBillingPosture::fromStored($custom['base_posture']);
            }
        }

        return ConcernBillingPosture::fromStored(is_string($value) ? $value : null);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof ConcernBillingPosture) {
            return $value->value;
        }

        if (is_string($value) && $value !== '') {
            if (ConcernBillingPosture::tryFrom($value) instanceof ConcernBillingPosture) {
                return ConcernBillingPosture::fromStored($value)->value;
            }

            if (ShopSettings::current()->customLaborBillingClass($value) !== null) {
                return $value;
            }
        }

        return ConcernBillingPosture::fromStored(is_string($value) ? $value : null)->value;
    }
}
