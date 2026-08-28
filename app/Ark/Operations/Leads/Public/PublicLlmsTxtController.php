<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Growth\Seo\PublicLlmsTxtDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicLlmsTxtController
{
    public function __invoke(Request $request): Response
    {
        if (! PublicMarketingUrl::servesPublicSeo($request)) {
            abort(404);
        }

        return response(PublicLlmsTxtDocument::body(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
