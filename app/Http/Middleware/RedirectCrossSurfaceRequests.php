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

        if ($host === SurfaceRouting::appHost() && $this->isPortalPath($request)) {
            return redirect()->to(
                SurfaceRouting::urlForHost(SurfaceRouting::customerHost(), $uri),
            );
        }

        if (! SurfaceRouting::portalOnPublicHost() && $host === SurfaceRouting::portalHost() && $request->is('app', 'app/*', 'webhooks', 'webhooks/*', 'dashboard', 'profile', 'profile/*', 'repair-orders', 'repair-orders/*')) {
            return redirect()->to(
                SurfaceRouting::urlForHost(SurfaceRouting::appHost(), $uri),
            );
        }

        if (
            SurfaceRouting::publicHost() !== null
            && $host === SurfaceRouting::publicHost()
            && $request->is('app', 'app/*', 'webhooks', 'webhooks/*', 'dashboard', 'profile', 'profile/*', 'repair-orders', 'repair-orders/*')
        ) {
            return redirect()->to(
                SurfaceRouting::urlForHost(SurfaceRouting::appHost(), $uri),
            );
        }

        return $next($request);
    }

    private function isPortalPath(Request $request): bool
    {
        return $request->is(
            'portal',
            'portal/*',
            'go/*',
            'access',
            'access/*',
            'estimates/*',
            'pay/*',
            'home',
            'vehicles/*',
            'logout',
        );
    }
}
