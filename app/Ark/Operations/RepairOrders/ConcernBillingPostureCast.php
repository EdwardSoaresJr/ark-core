<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<ConcernBillingPosture, string> */
class ConcernBillingPostureCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ConcernBillingPosture
    {
        return ConcernBillingPosture::fromStored(is_string($value) ? $value : null);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof ConcernBillingPosture) {
            return $value->value;
        }

        return ConcernBillingPosture::fromStored(is_string($value) ? $value : null)->value;
    }
}
