<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Runtime\Surfaces\SurfaceRouting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicRobotsController
{
    public function __invoke(Request $request): Response
    {
        if (PublicMarketingUrl::servesPublicSeo($request)) {
            $sitemap = PublicMarketingUrl::baseUrl().'/sitemap.xml';

            $body = implode("\n", [
                'User-agent: *',
                'Allow: /',
                'Disallow: /portal/',
                'Disallow: /app/',
                'Disallow: /leads/thanks',
                '',
                'Sitemap: '.$sitemap,
                '',
            ]);

            return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $lines = ['User-agent: *', 'Disallow: /portal/'];

        if (SurfaceRouting::enabled() && $request->getHost() === SurfaceRouting::appHost()) {
            $lines[] = 'Disallow: /app/';
            $lines[] = 'Disallow: /repair-orders';
        }

        $lines[] = '';

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
