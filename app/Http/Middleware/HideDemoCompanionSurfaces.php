<?php

namespace App\Http\Middleware;

use App\Ark\Runtime\DemoInstall;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HideDemoCompanionSurfaces
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! DemoInstall::isDemo() || $request->is('app/learn/preview', 'app/learn/preview/*')) {
            return $next($request);
        }

        if ($request->is(
            'app/learn',
            'app/learn/*',
            'app/learn-team-progress',
            'app/platform',
            'app/platform/*',
            'app/settings/shop/ark-cloud',
            'app/settings/shop/ark-cloud/*',
            'cloud',
            'cloud/*',
        )) {
            return redirect()->to(auth()->check() ? route('operations.today') : route('login'));
        }

        return $next($request);
    }
}
