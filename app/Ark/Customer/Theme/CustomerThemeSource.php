<?php

declare(strict_types=1);

namespace App\Ark\Customer\Theme;

/**
 * Optional host seam: where the current shop's customer theme comes from (a hosted website builder,
 * for example). Unbound, every customer page uses CustomerTheme::DEFAULTS.
 */
interface CustomerThemeSource
{
    /**
     * The current shop's theme values (CustomerTheme::DEFAULTS keys), or null for Core's defaults.
     * Core validates every value; unknown or invalid ones fall back to the default.
     *
     * @return array<string, mixed>|null
     */
    public function theme(): ?array;
}
