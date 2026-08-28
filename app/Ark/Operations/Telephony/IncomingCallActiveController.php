<?php

namespace App\Ark\Operations\Telephony;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class IncomingCallActiveController
{
    public function __invoke(): JsonResponse
    {
        $payload = Cache::get(IncomingCallContextBroadcaster::cacheKey());

        return response()->json([
            'call' => is_array($payload) ? $payload : null,
        ]);
    }
}
