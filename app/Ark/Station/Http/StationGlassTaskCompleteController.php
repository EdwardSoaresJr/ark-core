<?php

namespace App\Ark\Station\Http;

use App\Ark\Operations\Work\AdvisorTask;
use App\Ark\Station\StationGlassTasksProjection;
use Illuminate\Http\JsonResponse;

final class StationGlassTaskCompleteController
{
    public function __invoke(AdvisorTask $task, StationGlassTasksProjection $tasks): JsonResponse
    {
        $tasks->complete($task);

        return response()->json(['ok' => true]);
    }
}
