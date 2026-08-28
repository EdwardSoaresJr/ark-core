<?php

namespace App\Http\Middleware;

use App\Ark\Runtime\Authorization\DevRolePretend;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyDevRolePretend
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            DevRolePretend::applyEffectiveRoles($user);
        }

        return $next($request);
    }
}
