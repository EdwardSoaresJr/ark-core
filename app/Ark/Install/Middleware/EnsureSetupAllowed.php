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
            // Poller must observe phase=complete; complete page remains available.
            if ($request->routeIs('install.complete', 'install.progress.status')
                || $request->is('setup/progress/status')) {
                return $next($request);
            }

            if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
                return redirect()->route('login');
            }

            abort(403, 'ARK is already installed.');
        }

        if (InstallationState::isActivelyInstalling()) {
            $onProgressSurface = $request->routeIs('install.progress', 'install.progress.status', 'install.run')
                || $request->is('setup/progress', 'setup/progress/*', 'setup/install');

            if (! $onProgressSurface) {
                return redirect()->route('install.progress');
            }
        }

        return $next($request);
    }
}
