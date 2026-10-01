<?php

namespace App\Ark\Install\Middleware;

use App\Ark\Install\EnsureFirstRunApplicationKey;
use App\Ark\Install\InstallationState;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Force file session/cache while ARK is not installed, and establish APP_KEY
 * early enough for EncryptCookies so /setup can render on Herd/local PHP.
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

        try {
            app(EnsureFirstRunApplicationKey::class)->ensure();
        } catch (RuntimeException $exception) {
            throw new HttpException(503, $exception->getMessage(), $exception);
        }

        return $next($request);
    }
}
