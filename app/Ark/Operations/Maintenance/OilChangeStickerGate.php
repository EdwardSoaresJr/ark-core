<?php

namespace App\Ark\Operations\Maintenance;

use App\Ark\Operations\RepairOrders\RepairOrder;

/**
 * Stickers may print with the tech ticket (Prepared / RO mileage).
 * Prefer MaintenanceServiceEvent when present — history still owns Installed truth.
 */
final class OilChangeStickerGate
{
    public static function currentEventForRepairOrder(RepairOrder $repairOrder): ?MaintenanceServiceEvent
    {
        $service = MaintenanceService::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('kind', MaintenanceServiceKind::EngineOil->value)
            ->whereNotNull('current_event_id')
            ->first();

        if ($service === null) {
            return null;
        }

        $event = $service->currentEvent;

        return $event !== null && $event->isCurrent() ? $event : null;
    }

    public static function activeServiceForRepairOrder(RepairOrder $repairOrder): ?MaintenanceService
    {
        return MaintenanceService::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('kind', MaintenanceServiceKind::EngineOil->value)
            ->whereIn('status', [
                MaintenanceServiceStatus::Active->value,
                MaintenanceServiceStatus::Confirmed->value,
            ])
            ->orderByDesc('id')
            ->first();
    }

    /** Tech ticket / bay print — not gated on Confirm Installed. */
    public static function canPrint(RepairOrder $repairOrder): bool
    {
        return true;
    }

    /** @deprecated use canPrint() — kept for call sites during transition */
    public static function canPrintFinal(RepairOrder $repairOrder): bool
    {
        return self::canPrint($repairOrder);
    }
}
