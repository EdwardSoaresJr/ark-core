<?php

namespace App\Ark\Operations\Maintenance;

/**
 * Service history from current MaintenanceServiceEvents only (Principle #2).
 */
final class VehicleEngineOilHistoryProjection
{
    /**
     * @return list<array{
     *     service_sequence: int,
     *     revision: int,
     *     oil_brand: ?string,
     *     viscosity: ?string,
     *     quantity_qt: ?string,
     *     filter_part: ?string,
     *     washer: ?string,
     *     service_mileage: ?int,
     *     next_due_mileage: ?int,
     *     confirmed_at: ?string,
     *     repair_order_id: int,
     *     corrected: bool
     * }>
     */
    public function forVehicle(int $vehicleId): array
    {
        $events = MaintenanceServiceEvent::query()
            ->current()
            ->where('vehicle_id', $vehicleId)
            ->where('kind', MaintenanceServiceKind::EngineOil->value)
            ->orderByDesc('service_sequence')
            ->orderByDesc('revision')
            ->get();

        return $events->map(function (MaintenanceServiceEvent $event): array {
            $hadPriorRevision = MaintenanceServiceEvent::query()
                ->where('vehicle_id', $event->vehicle_id)
                ->where('kind', $event->kind->value)
                ->where('service_sequence', $event->service_sequence)
                ->where('revision', '<', $event->revision)
                ->exists();

            return [
                'service_sequence' => (int) $event->service_sequence,
                'revision' => (int) $event->revision,
                'oil_brand' => $event->oil_brand,
                'viscosity' => $event->viscosity,
                'quantity_qt' => $event->quantity_qt !== null ? (string) $event->quantity_qt : null,
                'filter_part' => $event->filter_part,
                'washer' => $event->washer?->value,
                'service_mileage' => $event->service_mileage,
                'next_due_mileage' => $event->next_due_mileage,
                'confirmed_at' => $event->confirmed_at?->toIso8601String(),
                'repair_order_id' => (int) $event->repair_order_id,
                'corrected' => $hadPriorRevision || (int) $event->revision > 0,
            ];
        })->all();
    }
}
