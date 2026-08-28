<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Mobile\OperatorContinuityProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** One continuity snapshot — badge, moments, and next action for any mobile surface. */
final class MobileContinuityController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        OperatorContinuityProjection $continuity,
    ): JsonResponse {
        abort_unless($access->canUseMobile($request->user()), 403);

        return response()->json($continuity->forUser($request->user()));
    }
}
