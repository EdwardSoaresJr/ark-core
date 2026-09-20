<?php

namespace App\Http\Middleware;

use App\Ark\Runtime\Preferences\EcosystemDisplayTheme;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncEcosystemDisplayThemeCookie
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if (! $user instanceof User) {
            return $response;
        }

        EcosystemDisplayTheme::attachToResponse($response, $user->displayTheme());

        return $response;
    }
}
