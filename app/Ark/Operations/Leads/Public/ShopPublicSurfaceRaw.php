<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\Settings\ShopSettings;

/** Read/write the public_surface_settings JSON blob without dropping unknown keys. */
final class ShopPublicSurfaceRaw
{
    /**
     * @return array<string, mixed>
     */
    public static function read(): array
    {
        $stored = ShopSettings::current()->public_surface_settings;

        if (! is_array($stored) || $stored === []) {
            return PublicSurfaceSettings::DEFAULTS;
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function write(array $raw): void
    {
        ShopSettings::current()->update([
            'public_surface_settings' => $raw,
        ]);
    }
}
