<?php

namespace App\Http\Middleware;

use App\Ark\Operations\OperationsFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppointmentsSurfaceEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! OperationsFeatures::appointmentsEnabled()) {
            abort(404);
        }

        return $next($request);
    }
}
