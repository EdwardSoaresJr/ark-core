<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobilePhonePeopleProjection;
use App\Ark\Mobile\MobileStaffAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileTelephonyPeopleController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        MobilePhonePeopleProjection $people,
    ): JsonResponse {
        abort_unless($access->canAccessShopCommunications($request->user()), 403);

        return response()->json([
            'people' => $people->forUser($request->user()),
        ]);
    }
}
