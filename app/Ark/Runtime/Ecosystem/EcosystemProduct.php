<?php

namespace App\Ark\Runtime\Ecosystem;

enum EcosystemProduct: string
{
    case Operations = 'operations';
    case Arkademy = 'arkademy';
    case Platform = 'platform';

    public function label(): string
    {
        return match ($this) {
            self::Operations => 'Operations',
            self::Arkademy => 'ARKademy',
            self::Platform => 'Platform',
        };
    }

    public function oidcProductSlug(): ?string
    {
        return match ($this) {
            self::Operations => 'ark_sms',
            self::Arkademy => 'arkademy',
            self::Platform => null,
        };
    }
}
