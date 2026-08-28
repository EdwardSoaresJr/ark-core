<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Staff\SoloShopOperations;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AssignRepairOrderTechnician
{
    public function __construct(
        private readonly SoloShopOperations $soloShop,
        private readonly OperationalEventRecorder $events,
    ) {}

    /**
     * @param  array{assigned_technician_id?: int|string|null}  $data
     */
    public function validate(array $data): ?int
    {
        $validated = validator($data, [
            'assigned_technician_id' => ['nullable', Rule::exists('users', 'id')],
        ])->validate();

        return filled($validated['assigned_technician_id'] ?? null)
            ? (int) $validated['assigned_technician_id']
            : null;
    }

    public function assign(RepairOrder $repairOrder, ?int $technicianId, User $actor): ?User
    {
        if ($technicianId === null && ! $repairOrder->allowsTechnicianClear()) {
            throw ValidationException::withMessages([
                'assigned_technician_id' => 'Technician assignment cannot be cleared while work is in progress or waiting on parts. Reassign to another technician, or move the repair order back to Ready for Work first.',
            ]);
        }

        $technician = $technicianId === null ? null : User::query()->findOrFail($technicianId);

        if (! $this->soloShop->canAssignAsTechnician($technician)) {
            throw ValidationException::withMessages([
                'assigned_technician_id' => $this->soloShop->isSoloOwnerShop()
                    ? 'Assign repair orders only to active owner or technician users.'
                    : 'Assign repair orders only to active technician users.',
            ]);
        }

        return $this->applyAssignment($repairOrder, $technicianId, $technician, $actor);
    }

    /**
     * Auto-assign the shop's sole staff member as technician when none is set —
     * the solo / mobile-solo operator is the technician by reality, not by a
     * configurable default. No-op when a technician is already assigned or when
     * more than one staff user exists (then a human must choose).
     */
    public function assignSoleStaffUserIfApplicable(RepairOrder $repairOrder, ?User $actor = null): ?User
    {
        if ($repairOrder->assigned_technician_id !== null) {
            return null;
        }

        $sole = $this->soloShop->soleStaffUser();

        if ($sole === null) {
            return null;
        }

        return $this->applyAssignment($repairOrder, (int) $sole->getKey(), $sole, $actor ?? $sole, autoAssigned: true);
    }

    private function applyAssignment(RepairOrder $repairOrder, ?int $technicianId, ?User $technician, ?User $actor, bool $autoAssigned = false): ?User
    {
        $fromTechnicianId = $repairOrder->assigned_technician_id;

        if ($fromTechnicianId === $technicianId) {
            return $technician;
        }

        $repairOrder->forceFill([
            'assigned_technician_id' => $technicianId,
        ])->save();

        $this->events->record(
            OperationalEventName::RepairOrderTechnicianAssigned,
            $repairOrder,
            actor: $actor,
            payload: [
                'from_technician_id' => $fromTechnicianId,
                'to_technician_id' => $technicianId,
                'to_technician_name' => $technician?->name,
                'auto_assigned' => $autoAssigned,
            ],
        );

        return $technician;
    }
}
