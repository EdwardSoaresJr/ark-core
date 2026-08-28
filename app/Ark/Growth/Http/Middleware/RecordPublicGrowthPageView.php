<?php

namespace App\Ark\Growth\Http\Middleware;

use App\Ark\Growth\Sessions\PublicGrowthSurfaceRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RecordPublicGrowthPageView
{
    public function __construct(
        private readonly PublicGrowthSurfaceRecorder $recorder,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->recorder->recordPageView($request);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $response;
    }
}
