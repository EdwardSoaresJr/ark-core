<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileWorkProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileWorkController
{
    public function __invoke(Request $request, MobileWorkProjection $projection): JsonResponse
    {
        return response()->json($projection->forUser($request->user()));
    }
}
