<?php

namespace App\Ark\Station\Http;

use App\Ark\Station\SynthesizeStationAttentionNudgeAction;
use Illuminate\Http\JsonResponse;

final class StationAttentionNudgeController
{
    public function __invoke(SynthesizeStationAttentionNudgeAction $nudge): JsonResponse
    {
        return response()->json($nudge->handle());
    }
}
