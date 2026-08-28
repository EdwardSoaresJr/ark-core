<?php

namespace App\Ark\Growth\Http\Middleware;

use App\Ark\Growth\Redirects\RedirectResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApplyGrowthRedirects
{
    public function __construct(
        private readonly RedirectResolver $redirectResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('growth.redirects_enabled', true)) {
            return $next($request);
        }

        $redirect = $this->redirectResolver->resolve($request);

        if ($redirect === null) {
            return $next($request);
        }

        $this->redirectResolver->recordHit($redirect);

        if ($redirect->isGone()) {
            return response('', 410);
        }

        $target = $redirect->to_path ?? '/';
        if ($redirect->is_wildcard) {
            $suffix = substr($request->path(), strlen(trim($redirect->from_path, '*')));
            $target = rtrim((string) $redirect->to_path, '/').'/'.ltrim($suffix, '/');
        }

        return redirect($target, $redirect->status_code);
    }
}
