<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileOwnerBookendProjection;
use App\Ark\Operations\ShopExcellence\OwnerWorkspaceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class MobileOwnerBookendController
{
    public function __invoke(
        Request $request,
        MobileOwnerBookendProjection $projection,
    ): JsonResponse {
        abort_unless(OwnerWorkspaceAccess::allows($request->user()), 403);

        $date = $request->query('date');
        $shopDate = is_string($date) && $date !== ''
            ? Carbon::parse($date)->startOfDay()
            : null;

        return response()->json([
            'bookend' => $projection->forDate($shopDate),
        ]);
    }
}
