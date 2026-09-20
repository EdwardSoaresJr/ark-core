<?php

namespace App\Http\Middleware;

use App\Ark\Runtime\Surfaces\SessionCookieDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Align session (and CSRF) cookie Domain with the request host.
 *
 * Must run before StartSession. Reads session.host_shared_domain so Octane/long-lived
 * workers never lose the ops shared Domain after a company-host request.
 */
final class ConfigureSessionCookieDomain
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $shared = config('session.host_shared_domain');
        $shared = is_string($shared) ? $shared : null;

        config([
            'session.domain' => SessionCookieDomain::forHost($request->getHost(), $shared),
        ]);

        return $next($request);
    }
}
