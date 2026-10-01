<?php

namespace App\Ark\Operations\WorkAuthorization;

enum WorkAuthorizationPackageType: string
{
    case Testing = 'testing';

    public function label(): string
    {
        return match ($this) {
            self::Testing => 'Testing Package',
        };
    }
}
