<?php

namespace App\Ark\Install\Middleware;

use App\Ark\Install\InstallJourney;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceInstallJourney
{
    public function handle(Request $request, Closure $next): Response
    {
        $redirect = InstallJourney::redirectFor($request->route()?->getName());
        if ($redirect !== null) {
            return redirect()->route($redirect);
        }

        return $next($request);
    }
}
