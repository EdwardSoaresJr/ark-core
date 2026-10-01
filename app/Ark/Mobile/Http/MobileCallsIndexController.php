<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileCallsLibraryProjection;
use App\Ark\Mobile\MobileStaffAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileCallsIndexController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        MobileCallsLibraryProjection $projection,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null && $access->canAccessShopCommunications($user), 403);

        return response()->json($projection->forRequest($request));
    }
}
