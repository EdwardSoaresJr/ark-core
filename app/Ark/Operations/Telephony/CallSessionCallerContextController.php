<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Telephony\Projections\CallSessionCallerContextProjection;
use Illuminate\Http\JsonResponse;

class CallSessionCallerContextController
{
    public function __invoke(
        CallSession $callSession,
        CallSessionCallerContextProjection $projection,
    ): JsonResponse {
        $callSession->loadMissing(['customer', 'repairOrder', 'owner']);

        return response()->json($projection->forSession($callSession));
    }
}
