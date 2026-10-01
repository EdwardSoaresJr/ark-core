<?php

namespace App\Http\Middleware;

use App\Ark\Operations\ShopExcellence\OwnerWorkspaceAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerWorkspaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(OwnerWorkspaceAccess::allows($request->user()), 403);

        return $next($request);
    }
}
