<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileCommsHubProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileCommsHubController
{
    public function __invoke(Request $request, MobileCommsHubProjection $projection): JsonResponse
    {
        return response()->json($projection->forUser($request->user()));
    }
}
