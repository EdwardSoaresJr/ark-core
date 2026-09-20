<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class RepairOrderConcurrency
{
    public const FIELD = 'opened_estimate_version';

    public function openedVersion(RepairOrder $repairOrder): int
    {
        return (int) $repairOrder->estimate_version;
    }

    public function guard(Request $request, RepairOrder $repairOrder): void
    {
        $openedVersion = $request->input(self::FIELD);

        if ($openedVersion === null || $openedVersion === '') {
            return;
        }

        $repairOrder->refresh();

        if ((int) $openedVersion === (int) $repairOrder->estimate_version) {
            return;
        }

        $userId = (int) $request->user()?->id;
        $lastActorId = (int) $repairOrder->estimate_version_actor_id;

        if ($userId > 0 && $lastActorId === $userId) {
            return;
        }

        $exception = new RepairOrderEstimateConflictException($repairOrder);

        throw new HttpResponseException($exception->render($request));
    }
}
