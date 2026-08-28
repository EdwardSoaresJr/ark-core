<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Mobile\OperatorContinuityProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileContinuityBadgeController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        OperatorContinuityProjection $continuity,
    ): JsonResponse {
        abort_unless($access->canUseMobile($request->user()), 403);

        return response()->json($continuity->badgeForUser($request->user()));
    }
}
