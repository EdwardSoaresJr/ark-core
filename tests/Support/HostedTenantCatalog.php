<?php

use App\Ark\Runtime\HostedRuntime;

/**
 * Marks this process as a hosted shop runtime via the Landlord tenant-catalog contract.
 * Does not create discontinued ARK Platform / Box pairing rows.
 *
 * @return string Absolute path to the temporary catalog file (caller may unlink).
 */
function enableHostedTenantCatalog(): string
{
    $catalog = tempnam(sys_get_temp_dir(), 'ark-tenants-');
    if ($catalog === false) {
        throw new RuntimeException('Unable to create hosted tenant catalog fixture.');
    }

    putenv('ARK_HOSTED_TENANTS='.$catalog);
    $_ENV['ARK_HOSTED_TENANTS'] = $catalog;
    $_SERVER['ARK_HOSTED_TENANTS'] = $catalog;

    if (! HostedRuntime::catalogConfigured()) {
        throw new RuntimeException('Hosted tenant catalog fixture did not activate HostedRuntime.');
    }

    return $catalog;
}

function clearHostedTenantCatalog(?string $catalog = null): void
{
    putenv('ARK_HOSTED_TENANTS');
    unset($_ENV['ARK_HOSTED_TENANTS'], $_SERVER['ARK_HOSTED_TENANTS']);

    if (is_string($catalog) && $catalog !== '' && is_file($catalog)) {
        unlink($catalog);
    }
}
