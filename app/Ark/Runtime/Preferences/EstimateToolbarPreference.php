<?php

namespace App\Ark\Runtime\Preferences;

use App\Ark\Operations\LaborGuides\LaborGuideIntent;
use App\Ark\Operations\LaborGuides\LaborGuideProvider;
use App\Ark\Operations\Parts\PartsCatalogLinks;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class EstimateToolbarPreference
{
    public const KIND_PARTS = 'parts_catalog';

    public const KIND_LABOR = 'labor_guide';

    /**
     * @param  list<string>  $availableKeys
     */
    public static function resolve(?User $user, string $kind, array $availableKeys): ?string
    {
        if ($availableKeys === []) {
            return null;
        }

        $stored = self::stored($user, $kind);

        if ($stored !== null && in_array($stored, $availableKeys, true)) {
            return $stored;
        }

        if ($kind === self::KIND_LABOR && in_array(LaborGuideIntent::KEY, $availableKeys, true)) {
            return LaborGuideIntent::KEY;
        }

        return $availableKeys[0];
    }

    public static function persist(User $user, string $kind, string $key): void
    {
        $column = self::column($kind);

        if ($column === null || ! in_array($key, self::allowedKeys($kind, $user), true)) {
            return;
        }

        if (! Schema::hasColumn('users', $column)) {
            return;
        }

        $user->forceFill([$column => $key])->save();
    }

    public static function stored(?User $user, string $kind): ?string
    {
        $column = self::column($kind);

        if ($user === null || $column === null) {
            return null;
        }

        $value = $user->{$column};

        return filled($value) ? (string) $value : null;
    }

    /**
     * @return list<string>
     */
    public static function allowedKeys(string $kind, ?User $user = null): array
    {
        return match ($kind) {
            self::KIND_PARTS => PartsCatalogLinks::allowedKeys($user),
            self::KIND_LABOR => array_merge(
                [LaborGuideIntent::KEY],
                array_map(
                    static fn (LaborGuideProvider $provider): string => $provider->value,
                    LaborGuideProvider::cases(),
                ),
            ),
            default => [],
        };
    }

    private static function column(string $kind): ?string
    {
        return match ($kind) {
            self::KIND_PARTS => 'default_parts_catalog',
            self::KIND_LABOR => 'default_labor_guide',
            default => null,
        };
    }
}
