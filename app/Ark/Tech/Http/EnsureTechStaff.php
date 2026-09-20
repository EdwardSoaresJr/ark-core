<?php

namespace App\Ark\Tech\Http;

use App\Ark\Tech\TechStaffGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTechStaff
{
    public function __construct(
        private readonly TechStaffGate $gate,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! $this->gate->canUseTech($user)) {
            return response()->json(['message' => 'ARK Tech is for technicians.'], 403);
        }

        return $next($request);
    }
}
