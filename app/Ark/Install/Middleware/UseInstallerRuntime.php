<?php

namespace App\Ark\Install\Middleware;

use App\Ark\Install\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force file/array session+cache while ARK is not installed so setup works before MySQL.
 */
final class UseInstallerRuntime
{
    public function handle(Request $request, Closure $next): Response
    {
        if (InstallationState::isNotInstalled()) {
            config([
                'session.driver' => 'file',
                'cache.default' => 'file',
                'queue.default' => 'sync',
            ]);
        }

        return $next($request);
    }
}
