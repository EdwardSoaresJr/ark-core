<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventPortalSearchIndexing
{
    public const ROBOTS_DIRECTIVE = 'noindex, nofollow, noarchive';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', self::ROBOTS_DIRECTIVE, false);

        if ($request->routeIs('portal.estimates.show')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', false);
            $response->headers->set('Pragma', 'no-cache', false);
        }

        return $response;
    }
}
