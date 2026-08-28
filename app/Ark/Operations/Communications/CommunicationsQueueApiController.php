<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Telephony\CallSessionQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationsQueueApiController
{
    public function __invoke(
        Request $request,
        CallSessionQueue $callSessionQueue,
        CommunicationsQueueResolver $resolver,
    ): JsonResponse {
        $callSessionQueue->reconcileStaleLiveSessions();

        return response()->json($resolver->resolve($request->user()));
    }
}
