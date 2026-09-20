<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileAttentionProjection;
use App\Ark\Mobile\MobileStaffAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileAttentionIndexController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        MobileAttentionProjection $projection,
    ): JsonResponse {
        abort_unless($access->canViewShopAttention($request->user()), 403);

        return response()->json($projection->forUser($request->user()));
    }
}
