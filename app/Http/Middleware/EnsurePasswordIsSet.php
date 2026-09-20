<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsSet
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->hasPasswordSet()) {
            return $next($request);
        }

        if ($request->routeIs('account.setup', 'account.setup.store', 'logout')) {
            return $next($request);
        }

        return redirect()->route('account.setup');
    }
}
