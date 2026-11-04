<?php

declare(strict_types=1);

namespace App\Ark\Customer\Theme;

use Throwable;

final class CustomerThemes
{
    /** Resolved on each call (no memo), so a process that serves several shops never reuses one. */
    public static function current(): CustomerTheme
    {
        if (! app()->bound(CustomerThemeSource::class)) {
            return CustomerTheme::default();
        }

        try {
            return CustomerTheme::fromArray(app(CustomerThemeSource::class)->theme() ?? []);
        } catch (Throwable $e) {
            report($e);

            return CustomerTheme::default();
        }
    }
}
