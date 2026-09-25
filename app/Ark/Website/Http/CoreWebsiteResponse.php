<?php

namespace App\Ark\Website\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CoreWebsiteResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-ARK-Website', 'core');

        return $response;
    }
}
