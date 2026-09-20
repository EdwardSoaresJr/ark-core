<?php

namespace App\Ark\Runtime\Identity\Oidc;

enum OidcProduct: string
{
    case ArkSms = 'ark_sms';
    case Arkademy = 'arkademy';
    case Portal = 'portal';
    case ArkWebAdmin = 'ark_web_admin';

    public static function normalizeSlug(string $slug): string
    {
        return $slug === 'ark_v2' ? self::ArkSms->value : $slug;
    }

    /**
     * @return list<string>
     */
    public static function staffDefaultsForRole(string $roleName): array
    {
        return match ($roleName) {
            'admin', 'advisor', 'technician' => [
                self::ArkSms->value,
                self::Arkademy->value,
            ],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function allStaffSlugs(): array
    {
        return [
            self::ArkSms->value,
            self::Arkademy->value,
            self::ArkWebAdmin->value,
        ];
    }
}
