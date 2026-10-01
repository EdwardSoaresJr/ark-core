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
];
