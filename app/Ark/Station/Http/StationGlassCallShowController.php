<?php

namespace App\Ark\Station\Http;

use App\Ark\Station\StationGlassCallProjection;
use Illuminate\Http\JsonResponse;

final class StationGlassCallShowController
{
    public function __invoke(string $call, StationGlassCallProjection $projection): JsonResponse
    {
        $payload = $projection->forId((int) $call);
        if ($payload === null) {
            return response()->json(['message' => 'Call not found.'], 404);
        }

        return response()->json(['call' => $payload]);
    }
}
