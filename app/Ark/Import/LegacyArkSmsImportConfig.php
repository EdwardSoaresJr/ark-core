<?php

namespace App\Ark\Import;

final class LegacyArkSmsImportConfig
{
    /**
     * @return array{tables: array<string, string>, columns: array<string, array<string, string>>, customer_scope?: string}
     */
    public static function profile(): array
    {
        $profileKey = (string) config('legacy-arksms-import.profile', 'default');
        $profiles = config('legacy-arksms-import.profiles', []);

        if (isset($profiles[$profileKey])) {
            return $profiles[$profileKey];
        }

        return [
            'tables' => config('legacy-arksms-import.tables', []),
            'columns' => config('legacy-arksms-import.columns', []),
        ];
    }

    public static function table(string $entity): string
    {
        $profile = self::profile();

        return (string) ($profile['tables'][$entity] ?? $entity);
    }

    /**
     * @return array<string, string>
     */
    public static function columns(string $entity): array
    {
        $profile = self::profile();

        return $profile['columns'][$entity] ?? [];
    }

    public static function customerScope(): ?string
    {
        $profile = self::profile();

        return $profile['customer_scope'] ?? null;
    }
}
