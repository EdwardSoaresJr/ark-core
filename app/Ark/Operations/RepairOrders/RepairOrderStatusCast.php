<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<RepairOrderWorkflowStatus, string> */
final class RepairOrderStatusCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): RepairOrderWorkflowStatus
    {
        $slug = is_string($value) ? $value : (string) ($attributes[$key] ?? RepairOrderStatus::Draft->value);

        return new RepairOrderWorkflowStatus($slug);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return RepairOrderWorkflowStatus::from($value)->value;
    }
}
