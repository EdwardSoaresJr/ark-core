<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileGlobalSearchProjection;
use App\Ark\Mobile\MobileStaffAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileGlobalSearchController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        MobileGlobalSearchProjection $projection,
    ): JsonResponse {
        abort_unless($access->canViewCustomer($request->user()), 403);

        $query = trim((string) $request->query('q', ''));

        return response()->json($projection->forQuery($query, $request->user()));
    }
}
