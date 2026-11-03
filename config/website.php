<?php

$domain = strtolower(trim((string) env('WEBSITE_CUSTOM_DOMAIN', '')));
$siteHost = strtolower(trim((string) env('ARK_WEBSITE_HOST', '')));
$preferred = filter_var(env('WEBSITE_CANONICAL_USES_CUSTOM_DOMAIN', true), FILTER_VALIDATE_BOOLEAN);

$customDomains = [];
if ($domain !== '' && $siteHost !== '' && $domain !== $siteHost && ! str_starts_with($domain, 'www.')) {
    $customDomains[] = [
        'domain' => $domain,
        'site_host' => $siteHost,
        'preferred' => $preferred,
    ];
}

return [
    /*
     * Installation runtime only. Each entry maps one custom domain onto one
     * native ARK site host (website_sites.public_host). Not a second site,
     * and not SURFACE_PUBLIC_ALIASES.
     */
    'custom_domains' => $customDomains,

    /*
     * A notice shown above every public and sign-in page, for example a demonstration shop
     * (array{text: string, link_label?: string, link_url?: string}). Null shows nothing.
     * A host that serves several shops sets it per shop at runtime.
     */
    'public_notice' => null,

    /*
     * Online appointment requests ask for a phone or email code when the shop can send one.
     * False skips the code step and records the request unverified, as for a shop that can send
     * neither (for example a demonstration shop that must never contact anyone).
     */
    'book_identity_verification' => true,
];
