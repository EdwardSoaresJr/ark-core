<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\RepairOrders\RepairOrder;

final class MobileIntakeRepairOrderProjection
{
    /**
     * @return array<string, mixed>
     */
    public function created(RepairOrder $repairOrder): array
    {
        return [
            'repair_order' => $this->summary($repairOrder),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle', 'assignedTechnician', 'concerns']);

        $primaryConcern = $repairOrder->concerns
            ->sortBy(fn ($concern): array => [$concern->position, $concern->id])
            ->first();

        return [
            'repair_order_id' => $repairOrder->repair_order_id,
            'id' => $repairOrder->repair_order_id,
            'status' => $repairOrder->status->value,
            'status_label' => $repairOrder->status->label(),
            'status_tone' => MobileRepairOrderStatusTone::forStatus($repairOrder->status),
            'customer_name' => $repairOrder->customer?->name,
            'vehicle_label' => $repairOrder->vehicle?->display_name ?? 'Vehicle',
            'concern_count' => $repairOrder->concerns->count(),
            'primary_concern_id' => $primaryConcern?->id,
            'visit_reason' => $repairOrder->visit_reason,
            'assigned_technician_id' => $repairOrder->assigned_technician_id,
            'assigned_technician' => $repairOrder->assignedTechnician?->name,
        ];
    }
}
