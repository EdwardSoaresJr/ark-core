<?php

namespace App\Ark\Operations\Financial;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Known methods stay on the enum. A shop-added method is stored as its key.
 *
 * @implements CastsAttributes<PaymentMethod|string|null, PaymentMethod|string|null>
 */
final class PaymentMethodCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): PaymentMethod|string|null
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return PaymentMethod::tryFrom($value) ?? $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof PaymentMethod) {
            return $value->value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
