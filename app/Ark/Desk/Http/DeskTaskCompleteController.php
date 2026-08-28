<?php

namespace App\Ark\Desk\Http;

use App\Ark\Operations\Work\AdvisorTask;
use App\Ark\Station\StationGlassTasksProjection;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeskTaskCompleteController
{
    public function __invoke(Request $request, AdvisorTask $task, StationGlassTasksProjection $tasks): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless(
            (int) $task->assigned_user_id === (int) $user->id
            || (int) $task->created_by_user_id === (int) $user->id,
            403,
        );
        $tasks->complete($task);

        return response()->json(['completed' => true, 'id' => $task->id]);
    }
}
