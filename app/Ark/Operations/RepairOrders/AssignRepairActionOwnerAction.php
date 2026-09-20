<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Staff\SoloShopOperations;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * R1 — RepairActionOwner (Technician only). Ownership transfers; never copies.
 */
final class AssignRepairActionOwnerAction
{
    public function __construct(
        private readonly SoloShopOperations $soloShop,
        private readonly OperationalEventRecorder $events,
    ) {}

    public function assign(
        RepairOrderWorkGroup $workGroup,
        ?int $technicianUserId,
        User $actor,
        ?string $reason = null,
    ): RepairOrderWorkGroup {
        if ($technicianUserId === null) {
            throw ValidationException::withMessages([
                'owner_user_id' => 'A Repair Action must have exactly one owning technician.',
            ]);
        }

        $technician = User::query()->findOrFail($technicianUserId);

        if (! $this->soloShop->canAssignAsTechnician($technician)) {
            throw ValidationException::withMessages([
                'owner_user_id' => $this->soloShop->isSoloOwnerShop()
                    ? 'Assign ownership only to active owner or technician users.'
                    : 'Assign ownership only to active technician users.',
            ]);
        }

        $fromType = $workGroup->owner_type;
        $fromUserId = $workGroup->owner_user_id !== null ? (int) $workGroup->owner_user_id : null;
        $toType = RepairActionOwnerType::Technician;
        $toUserId = (int) $technician->id;

        if ($fromType === $toType && $fromUserId === $toUserId) {
            return $workGroup;
        }

        $eventKind = $fromUserId === null
            ? RepairActionOwnershipEvent::KIND_ASSIGNED
            : RepairActionOwnershipEvent::KIND_TRANSFERRED;

        $occurredAt = now();
        $fromTypeValue = $fromType instanceof RepairActionOwnerType ? $fromType->value : (is_string($fromType) ? $fromType : null);

        DB::transaction(function () use (
            $workGroup,
            $toType,
            $toUserId,
            $fromTypeValue,
            $fromUserId,
            $eventKind,
            $actor,
            $reason,
            $occurredAt,
        ): void {
            $workGroup->forceFill([
                'owner_type' => $toType,
                'owner_user_id' => $toUserId,
            ])->save();

            RepairActionOwnershipEvent::query()->create([
                'repair_order_work_group_id' => $workGroup->id,
                'event_kind' => $eventKind,
                'from_owner_type' => $fromTypeValue,
                'from_owner_user_id' => $fromUserId,
                'to_owner_type' => $toType->value,
                'to_owner_user_id' => $toUserId,
                'reason' => filled($reason) ? trim($reason) : null,
                'actor_user_id' => $actor->id,
                'occurred_at' => $occurredAt,
            ]);
        });

        $workGroup->loadMissing('concern.repairOrder');
        $repairOrder = $workGroup->concern?->repairOrder;

        if ($repairOrder !== null) {
            $this->events->record(
                OperationalEventName::RepairActionOwnerChanged,
                $repairOrder,
                actor: $actor,
                payload: [
                    'repair_order_work_group_id' => $workGroup->id,
                    'work_group_title' => $workGroup->title,
                    'event_kind' => $eventKind,
                    'from_owner_type' => $fromTypeValue,
                    'from_owner_user_id' => $fromUserId,
                    'to_owner_type' => $toType->value,
                    'to_owner_user_id' => $toUserId,
                    'to_owner_name' => $technician->name,
                    'reason' => filled($reason) ? trim($reason) : null,
                ],
            );
        }

        return $workGroup->fresh(['ownerUser', 'ownershipEvents']);
    }

    /**
     * Seed owner from transitional RO Primary Technician column when creating a new Repair Action.
     * Only remaining production read of assigned_technician_id for ownership defaulting.
     */
    public function seedFromPrimaryTechnician(
        RepairOrderWorkGroup $workGroup,
        RepairOrder $repairOrder,
        ?User $actor = null,
    ): void {
        if ($workGroup->owner_user_id !== null) {
            return;
        }

        $primaryId = $repairOrder->assigned_technician_id;
        if ($primaryId === null) {
            return;
        }

        $actor ??= $repairOrder->assignedTechnician ?? User::query()->find($primaryId);
        if ($actor === null) {
            return;
        }

        $this->assign($workGroup, (int) $primaryId, $actor, reason: 'Inherited from Primary Technician');
    }
}
