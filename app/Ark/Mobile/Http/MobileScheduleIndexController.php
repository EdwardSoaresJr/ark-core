<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileScheduleProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\OperationsFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class MobileScheduleIndexController
{
    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        MobileScheduleProjection $projection,
    ): JsonResponse {
        abort_unless($access->canPerformIntake($request->user()), 403);
        abort_unless(OperationsFeatures::appointmentsEnabled(), 404);

        $date = $request->query('date');
        $day = is_string($date) && $date !== ''
            ? Carbon::parse($date)->startOfDay()
            : now()->startOfDay();

        return response()->json($projection->forDay($day, $request->user()));
    }
}
