<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileNotificationsProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileNotificationsIndexController
{
    public function __invoke(Request $request, MobileNotificationsProjection $projection): JsonResponse
    {
        return response()->json($projection->forUser($request->user()));
    }
}
