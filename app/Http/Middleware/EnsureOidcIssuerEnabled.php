<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureOidcIssuerEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('oidc.enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
