<?php

namespace App\Ark\Operations\Parts;

enum CustomerDescriptionSource: string
{
    case Generated = 'generated';
    case Manual = 'manual';

    public function isManual(): bool
    {
        return $this === self::Manual;
    }

    public static function tryFromStored(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return self::tryFrom($normalized);
    }
}
