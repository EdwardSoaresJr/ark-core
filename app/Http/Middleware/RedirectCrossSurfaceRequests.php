<?php

namespace App\Http\Middleware;

use App\Ark\Operations\Learn\ArkademyUrls;
use App\Ark\Runtime\Surfaces\SurfaceRouting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectCrossSurfaceRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $uri = $request->getRequestUri();

        // Company product: www → apex (ARK Cloud).
        $companyHost = SurfaceRouting::companyHost();
        $companyWww = SurfaceRouting::companyWwwHost();
        if ($companyHost !== null && $companyWww !== null && $host === $companyWww) {
            return redirect()->to(
                SurfaceRouting::urlForHost($companyHost, $uri),
                301,
            );
        }

        $publicHost = SurfaceRouting::publicHost();
        $publicWww = SurfaceRouting::publicWwwHost();
        if ($publicHost !== null && $publicWww !== null && $host === $publicWww) {
            return redirect()->to(
                SurfaceRouting::urlForHost($publicHost, $uri),
                301,
            );
        }

        if (! SurfaceRouting::enabled()) {
            return $next($request);
        }

        if ($host === SurfaceRouting::learnHost() && ! ArkademyUrls::isCutover()) {
            $suffix = $request->path() === '/' ? '' : '/'.$request->path();

            return redirect()->to(
                SurfaceRouting::urlForHost(SurfaceRouting::appHost(), '/app/learn'.$suffix),
            );
        }

        if (SurfaceRouting::portalOnPublicHost() && $host === SurfaceRouting::portalHost()) {
            return redirect()->to(
                SurfaceRouting::urlForHost((string) SurfaceRouting::publicHost(), $uri),
                301,
            );
        }

        if ($host === SurfaceRouting::appHost() && $this->isSignedInCustomerPortalPath($request)) {
            return redirect()->to(
                SurfaceRouting::urlForHost(SurfaceRouting::customerHost(), $uri),
            );
        }

        if (! SurfaceRouting::portalOnPublicHost() && $host === SurfaceRouting::portalHost() && $request->is('app', 'app/*', 'webhooks', 'webhooks/*', 'dashboard', 'profile', 'profile/*', 'repair-orders', 'repair-orders/*')) {
            return redirect()->to(
                SurfaceRouting::urlForHost(SurfaceRouting::appHost(), $uri),
            );
        }

        // Custom / public website host hitting ops paths → permanent ARK URL.
        // Skip when website and ops share one host (Hosted default URL).
        if (
            SurfaceRouting::isPublicHost($host)
            && $host !== SurfaceRouting::appHost()
            && $request->is('app', 'app/*', 'webhooks', 'webhooks/*', 'dashboard', 'profile', 'profile/*', 'repair-orders', 'repair-orders/*')
        ) {
            return redirect()->to(
                SurfaceRouting::urlForHost(SurfaceRouting::appHost(), $uri),
            );
        }

        return $next($request);
    }

    private function isSignedInCustomerPortalPath(Request $request): bool
    {
        return $request->is(
            'portal',
            'portal/access',
            'portal/access/*',
            'portal/home',
            'portal/vehicles',
            'portal/vehicles/*',
            'portal/logout',
            'access',
            'access/*',
            'home',
            'vehicles',
            'vehicles/*',
            'logout',
        );
    }
}
