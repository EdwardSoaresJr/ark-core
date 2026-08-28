<?php

namespace App\Http\Middleware;

use App\Ark\Operations\Leads\Public\PublicLegacyRedirect;
use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectPublicLegacyUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        if (! PublicMarketingUrl::isPublicMarketingHost($request)) {
            return $next($request);
        }

        $target = PublicLegacyRedirect::resolve($request->path());

        if ($target === null) {
            return $next($request);
        }

        return redirect()->to($target, 301);
    }
}
