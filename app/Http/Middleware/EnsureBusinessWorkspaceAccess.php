<?php

namespace App\Http\Middleware;

use App\Ark\Operations\Business\BusinessWorkspaceAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessWorkspaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(BusinessWorkspaceAccess::allows($request->user()), 403);

        return $next($request);
    }
}
