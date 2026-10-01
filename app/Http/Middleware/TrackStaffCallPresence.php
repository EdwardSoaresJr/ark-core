<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackStaffCallPresence
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $request->is('app', 'app/*')) {
            $request->attributes->set(
                'operations.previous_last_seen_at',
                $user->last_seen_at,
            );
        }

        return $next($request);
    }
}
