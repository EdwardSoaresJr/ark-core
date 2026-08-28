<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepairOrderWorksheetSessionController
{
    public function heartbeat(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderWorksheetPresence $presence,
        RepairOrderConcurrency $concurrency,
    ): JsonResponse {
        $data = $request->validate([
            'session_token' => ['required', 'string', 'max:64'],
            'surface' => ['required', 'string', 'in:builder,review'],
            'opened_estimate_version' => ['nullable', 'integer', 'min:1'],
        ]);

        $session = $presence->touch(
            $repairOrder,
            $request->user(),
            $data['session_token'],
            $data['surface'],
        );

        $repairOrder->refresh();
        $openedVersion = isset($data['opened_estimate_version']) ? (int) $data['opened_estimate_version'] : null;
        $currentVersion = (int) $repairOrder->estimate_version;
        $changedByCurrentUser = (int) $repairOrder->estimate_version_actor_id === (int) $request->user()?->id;
        $versionDrifted = $openedVersion !== null
            && $openedVersion !== $currentVersion
            && ! $changedByCurrentUser;

        return response()->json([
            'estimate_version' => $currentVersion,
            'lease_expires_at' => $session->expires_at?->toIso8601String(),
            'lease_valid' => ! $session->isExpired(),
            'version_drifted' => $versionDrifted,
            'presence_message' => $presence->presenceMessage(
                $repairOrder,
                $request->user(),
                $data['session_token'],
            ),
            'sessions' => $presence->presentUsers(
                $repairOrder,
                $request->user(),
                $data['session_token'],
            ),
            'conflict' => $versionDrifted
                ? (new RepairOrderEstimateConflictException($repairOrder))->payload()
                : null,
        ]);
    }

    public function release(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderWorksheetPresence $presence,
    ): JsonResponse {
        if ($request->isJson()) {
            $data = $request->validate([
                'session_token' => ['required', 'string', 'max:64'],
            ]);
        } else {
            $data = $request->validate([
                'session_token' => ['required', 'string', 'max:64'],
            ]);
        }

        $presence->release($repairOrder, $data['session_token']);

        return response()->json(['released' => true]);
    }
}
