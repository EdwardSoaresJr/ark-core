<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileOrientationProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileOrientationController
{
    public function __invoke(Request $request, MobileOrientationProjection $projection): JsonResponse
    {
        return response()->json($projection->forUser($request->user()));
    }
}
