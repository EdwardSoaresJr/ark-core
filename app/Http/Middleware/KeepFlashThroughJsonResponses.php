<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Flash data belongs to the next page the user sees. Background JSON requests (autocomplete, heartbeats, polls) can
 * land between a save and its redirected page; without this they age the flash away, and the page loses its status
 * message and worksheet continuity scope.
 */
class KeepFlashThroughJsonResponses
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->hasSession() && $this->cannotShowFlash($response)) {
            $request->session()->reflash();
        }

        return $response;
    }

    private function cannotShowFlash(Response $response): bool
    {
        return $response instanceof JsonResponse
            || str_contains((string) $response->headers->get('Content-Type'), 'json')
            || $response->getStatusCode() === Response::HTTP_NO_CONTENT;
    }
}
