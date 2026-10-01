<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileConversationsProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileConversationsIndexController
{
    public function __invoke(Request $request, MobileConversationsProjection $projection): JsonResponse
    {
        return response()->json($projection->threadsForUser($request->user()));
    }
}
