<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Authorized waiver of a Required inspection for one RO.
 * Optional / Recommended jobs must not need a waiver to close.
 */
final class WaiveRepairOrderInspectionAction
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
    ) {}

    public static function canWaive(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasRole(ArkRole::Admin->value);
    }

    public function execute(RepairOrder $repairOrder, User $actor, string $reason): RepairOrder
    {
        abort_unless(self::canWaive($actor), 403);
        $repairOrder->ensureOpenForEditing();

        $trimmed = trim($reason);
        if ($trimmed === '' || mb_strlen($trimmed) < 8) {
            throw ValidationException::withMessages([
                'inspection_waived_reason' => 'Enter a short reason (at least 8 characters) for the audit trail.',
            ]);
        }

        $decision = app(InspectionRequirementResolver::class)->forRepairOrder($repairOrder);
        if ($decision->waived) {
            return $repairOrder;
        }

        if ($decision->requirement !== InspectionRequirement::Required) {
            throw ValidationException::withMessages([
                'inspection_waived_reason' => 'Only a Required inspection needs a waiver. Optional and Recommended jobs can close without one.',
            ]);
        }

        $repairOrder->forceFill([
            'inspection_waived_at' => now(),
            'inspection_waived_by' => $actor->id,
            'inspection_waived_reason' => $trimmed,
        ])->save();

        $this->events->record(
            OperationalEventName::InspectionRequirementWaived,
            $repairOrder,
            actor: $actor,
            payload: [
                'reason' => $trimmed,
                'requirement' => InspectionRequirement::Required->value,
            ],
        );

        return $repairOrder->fresh();
    }

    public function clear(RepairOrder $repairOrder, User $actor): RepairOrder
    {
        abort_unless(self::canWaive($actor), 403);
        $repairOrder->ensureOpenForEditing();

        $priorReason = $repairOrder->getAttribute('inspection_waived_reason');

        $repairOrder->forceFill([
            'inspection_waived_at' => null,
            'inspection_waived_by' => null,
            'inspection_waived_reason' => null,
        ])->save();

        $this->events->record(
            OperationalEventName::InspectionWaiverCleared,
            $repairOrder,
            actor: $actor,
            payload: [
                'previous_reason' => is_string($priorReason) && $priorReason !== '' ? $priorReason : null,
            ],
        );

        return $repairOrder->fresh();
    }
}
