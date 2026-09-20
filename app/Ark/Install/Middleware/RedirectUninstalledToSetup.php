<?php

namespace App\Ark\Install\Middleware;

use App\Ark\Install\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectUninstalledToSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if (InstallationState::isInstalled()) {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        return redirect()->route('install.welcome');
    }

    private function isExempt(Request $request): bool
    {
        if ($request->is('setup', 'setup/*')) {
            return true;
        }

        if ($request->is('up', 'up/*')) {
            return true;
        }

        // Static assets / vite in local.
        if ($request->is('build/*', 'assets/*', 'favicon*', 'hot')) {
            return true;
        }

        return false;
    }
}
