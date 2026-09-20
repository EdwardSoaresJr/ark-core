<?php

namespace App\Ark\Station\Http;

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionQueue;
use App\Ark\Operations\Work\AdvisorTask;
use App\Ark\Station\StationGlassTasksProjection;
use Illuminate\Http\JsonResponse;

final class StationGlassCallHandleController
{
    public function __invoke(
        string $call,
        CallSessionQueue $queue,
        StationGlassTasksProjection $tasks,
    ): JsonResponse {
        $session = CallSession::query()->find((int) $call);
        if ($session === null) {
            return response()->json(['message' => 'Call not found.'], 404);
        }

        $queue->markCallerHandled($session);

        AdvisorTask::query()
            ->whereNull('completed_at')
            ->where('call_session_id', $session->id)
            ->get()
            ->each(fn (AdvisorTask $task) => $tasks->complete($task));

        return response()->json(['ok' => true]);
    }
}
