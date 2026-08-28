<?php

namespace App\Ark\Station\Http;

use App\Ark\Station\StationDeviceToken;
use App\Ark\Station\StationGlassConfig;
use App\Ark\Station\StationGlassTasksProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StationGlassTaskStoreController
{
    public function __invoke(
        Request $request,
        StationGlassTasksProjection $tasks,
        StationGlassConfig $config,
    ): JsonResponse {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:1000'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'repair_order_id' => ['nullable', 'integer'],
            'call_session_id' => ['nullable', 'integer', 'exists:call_sessions,id'],
            'due_at' => ['nullable', 'date'],
        ]);

        /** @var StationDeviceToken $token */
        $token = $request->attributes->get(AuthenticateStationDevice::REQUEST_ATTR);
        $glass = $config->forToken($token);
        $creator = $data['assigned_user_id'] ?? $glass['primary_advisor_user_id'] ?? null;

        $task = $tasks->claimOrCreate($data, is_int($creator) ? $creator : null);

        return response()->json(['task' => $tasks->present($task->load(['repairOrder.vehicle']))]);
    }
}
