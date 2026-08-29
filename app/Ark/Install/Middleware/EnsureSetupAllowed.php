<?php

namespace App\Ark\Install\Middleware;

use App\Ark\Install\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSetupAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (InstallationState::isInstalled()) {
            if ($request->routeIs('install.complete')) {
                return $next($request);
            }

            if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
                return redirect()->route('login');
            }

            abort(403, 'ARK is already installed.');
        }

        return $next($request);
    }
}
