<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Previous visits for the same customer — suggestion list + link map.
 * Disposable. Rebuild from RepairOrder rows.
 */
final class PriorVisitMentionProjection
{
    /**
     * @return array{suggestions: list<array<string, mixed>>, href_by_number: array<int, string>}
     */
    public static function for(
        ?int $customerId,
        ?int $excludeRepairOrderId = null,
        ?int $preferVehicleId = null,
        int $suggestionLimit = 15,
    ): array {
        if ($customerId === null || $customerId < 1) {
            return [
                'suggestions' => [],
                'href_by_number' => [],
            ];
        }

        $rows = RepairOrder::query()
            ->with('vehicle')
            ->where('customer_id', $customerId)
            ->when(
                $excludeRepairOrderId !== null,
                fn ($query) => $query->where('id', '!=', $excludeRepairOrderId),
            )
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->get();

        $hrefByNumber = [];

        foreach ($rows as $repairOrder) {
            $number = (int) $repairOrder->repair_order_id;

            if ($number < 1) {
                continue;
            }

            $hrefByNumber[$number] = route('operations.repair-orders.show', $repairOrder);
        }

        $sorted = $rows
            ->sortBy(function (RepairOrder $repairOrder) use ($preferVehicleId): array {
                $sameVehicle = $preferVehicleId !== null
                    && (int) $repairOrder->vehicle_id === $preferVehicleId
                    ? 0
                    : 1;
                $opened = $repairOrder->opened_at instanceof Carbon
                    ? -$repairOrder->opened_at->getTimestamp()
                    : -$repairOrder->id;

                return [$sameVehicle, $opened];
            })
            ->values();

        $suggestions = [];

        foreach ($sorted->take($suggestionLimit) as $repairOrder) {
            $number = (int) $repairOrder->repair_order_id;

            if ($number < 1) {
                continue;
            }

            $when = $repairOrder->opened_at instanceof Carbon
                ? $repairOrder->opened_at->timezone(config('app.timezone'))->format('M j, Y')
                : '';
            $vehicle = trim((string) ($repairOrder->vehicle?->display_name ?? ''));
            $reason = trim((string) ($repairOrder->visit_reason ?: $repairOrder->concern_summary ?: ''));

            $parts = array_values(array_filter([$when, $vehicle !== '' ? $vehicle : null, $reason !== '' ? Str::limit($reason, 48) : null]));

            $suggestions[] = [
                'number' => $number,
                'token' => RepairOrderMention::token($number),
                'label' => 'RO '.$number,
                'detail' => implode(' · ', $parts),
                'same_vehicle' => $preferVehicleId !== null
                    && (int) $repairOrder->vehicle_id === $preferVehicleId,
            ];
        }

        return [
            'suggestions' => $suggestions,
            'href_by_number' => $hrefByNumber,
        ];
    }
}
