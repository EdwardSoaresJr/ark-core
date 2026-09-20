<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * R1.1 — Repair Action operational communication: status + replaceable Latest Update.
 */
final class UpdateRepairActionCommunicationAction
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
    ) {}

    public function update(
        RepairOrderWorkGroup $workGroup,
        User $actor,
        ?RepairActionStatus $status = null,
        ?string $latestUpdate = null,
        bool $clearLatestUpdate = false,
    ): RepairOrderWorkGroup {
        if ($status === null && $latestUpdate === null && ! $clearLatestUpdate) {
            throw ValidationException::withMessages([
                'status' => 'Provide a status or Latest Update.',
            ]);
        }

        $normalizedUpdate = null;
        if ($latestUpdate !== null) {
            $normalizedUpdate = RepairOrderWorkGroup::normalizeLatestUpdate($latestUpdate);
            if ($normalizedUpdate === null) {
                $clearLatestUpdate = true;
            }
        }

        $priorStatus = $workGroup->status instanceof RepairActionStatus
            ? $workGroup->status
            : RepairActionStatus::fromStored(is_string($workGroup->status) ? $workGroup->status : null);
        $priorUpdate = $workGroup->latest_update;

        $nextStatus = $status ?? $priorStatus;
        $nextUpdate = $clearLatestUpdate
            ? null
            : ($normalizedUpdate !== null ? $normalizedUpdate : $priorUpdate);

        if ($nextStatus === $priorStatus && $nextUpdate === $priorUpdate) {
            return $workGroup;
        }

        DB::transaction(function () use ($workGroup, $nextStatus, $nextUpdate): void {
            $workGroup->forceFill([
                'status' => $nextStatus,
                'latest_update' => $nextUpdate,
            ])->save();
        });

        $workGroup->loadMissing('concern.repairOrder');
        $repairOrder = $workGroup->concern?->repairOrder;

        if ($repairOrder !== null) {
            $this->events->record(
                OperationalEventName::RepairActionCommunicationUpdated,
                $repairOrder,
                actor: $actor,
                payload: [
                    'repair_order_work_group_id' => $workGroup->id,
                    'work_group_title' => $workGroup->title,
                    'prior_status' => $priorStatus->value,
                    'status' => $nextStatus->value,
                    'latest_update_changed' => $nextUpdate !== $priorUpdate,
                    'latest_update' => $nextUpdate,
                ],
            );
        }

        return $workGroup->fresh(['ownerUser']);
    }
}
