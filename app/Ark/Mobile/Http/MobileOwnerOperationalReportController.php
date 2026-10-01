<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileOwnerOperationalReportProjection;
use App\Ark\Operations\ShopExcellence\OwnerWorkspaceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileOwnerOperationalReportController
{
    public function __invoke(
        Request $request,
        MobileOwnerOperationalReportProjection $projection,
    ): JsonResponse {
        abort_unless(OwnerWorkspaceAccess::allows($request->user()), 403);

        $from = $request->query('from');
        $to = $request->query('to');

        return response()->json([
            'report' => $projection->forRange(
                is_string($from) && $from !== '' ? $from : null,
                is_string($to) && $to !== '' ? $to : null,
            ),
        ]);
    }
}
