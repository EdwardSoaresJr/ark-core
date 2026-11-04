<?php

declare(strict_types=1);

namespace App\Ark\Customer;

/**
 * Optional host seam: the shop website's own page links for the customer header and footer, so the
 * portal, booking, and website share one navigation. Unbound (or null), Core lists its website pages.
 * Request an appointment and Sign In / My Account are always added by Core.
 */
interface CustomerSiteNavigation
{
    /**
     * @return list<array{label: string, href: string, active: bool}>|null
     */
    public function links(): ?array;
}
