<?php

namespace App\Http\Middleware;

use App\Ark\Runtime\DemoInstall;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PresentPublicDemo
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! DemoInstall::isDemo() || ! $request->isMethod('GET') || $request->path() !== '/') {
            return $next($request);
        }

        if ($request->user() !== null) {
            return $next($request);
        }

        return response()->view('demo.gate', [
            'enterUrl' => route('login'),
            'githubUrl' => route('demo.github', ['source_path' => '/']),
            'hostedUrl' => route('demo.github', ['source_path' => '/', 'to' => 'hosted']),
        ]);
    }
}
