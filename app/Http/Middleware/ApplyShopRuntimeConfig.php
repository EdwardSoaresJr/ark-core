<?php

namespace App\Http\Middleware;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopIntegrationRuntimeConfig;
use App\Ark\Runtime\HostedRuntime;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after the hosted tenant connection is selected.
 * Boot cannot read shop settings yet, because that connection is still the image default.
 */
final class ApplyShopRuntimeConfig
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (HostedRuntime::tenantDatabaseSelected()) {
            ShopIntegrationRuntimeConfig::apply();
            ShopDisplayTimezone::apply();
        }

        return $next($request);
    }
}
