<?php

namespace App\Ark\Desk\Http;

use App\Ark\Station\StationGlassTasksProjection;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeskTaskStoreController
{
    public function __invoke(Request $request, StationGlassTasksProjection $tasks): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:1000'],
            'repair_order_id' => ['nullable', 'integer'],
            'call_session_id' => ['nullable', 'integer', 'exists:call_sessions,id'],
            'due_at' => ['nullable', 'date'],
        ]);
        $data['assigned_user_id'] = $user->id;
        $task = $tasks->claimOrCreate($data, $user->id);

        return response()->json(['task' => $tasks->present($task->load(['repairOrder.vehicle', 'customer']))]);
    }
}
