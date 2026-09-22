<?php

namespace App\Ark\Operations\Communications;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationsQueueApiController
{
    public function __invoke(
        Request $request,
        CommunicationsQueueResolver $resolver,
    ): JsonResponse {
        return response()->json($resolver->resolve($request->user()));
    }
}
