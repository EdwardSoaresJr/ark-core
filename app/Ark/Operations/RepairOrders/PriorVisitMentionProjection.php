<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Recommendations\Recommendation;
use App\Ark\Operations\Recommendations\RecommendationLifecycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Previous visits and deferred work for this vehicle — suggestion list + link map.
 * Disposable. Rebuild from RepairOrder / Recommendation rows.
 */
final class PriorVisitMentionProjection
{
    /**
     * @return array{
     *     suggestions: list<array<string, mixed>>,
     *     href_by_number: array<int, string>,
     *     deferred: list<array<string, mixed>>,
     * }
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
                'deferred' => [],
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

        $vehicleRows = $preferVehicleId !== null && $preferVehicleId > 0
            ? $rows->filter(
                fn (RepairOrder $repairOrder): bool => (int) $repairOrder->vehicle_id === $preferVehicleId,
            )->values()
            : collect();

        $suggestions = [];

        foreach ($vehicleRows->take($suggestionLimit) as $repairOrder) {
            $number = (int) $repairOrder->repair_order_id;

            if ($number < 1) {
                continue;
            }

            $when = $repairOrder->opened_at instanceof Carbon
                ? $repairOrder->opened_at->timezone(config('app.timezone'))->format('M j, Y')
                : '';
            $reason = trim((string) ($repairOrder->visit_reason ?: $repairOrder->concern_summary ?: ''));
            $parts = array_values(array_filter([$when, $reason !== '' ? Str::limit($reason, 48) : null]));

            $suggestions[] = [
                'number' => $number,
                'token' => RepairOrderMention::token($number),
                'label' => 'RO '.$number,
                'detail' => implode(' · ', $parts),
                'same_vehicle' => true,
            ];
        }

        $currentRepairOrder = $excludeRepairOrderId !== null
            ? RepairOrder::query()->find($excludeRepairOrderId)
            : null;

        return [
            'suggestions' => $suggestions,
            'href_by_number' => $hrefByNumber,
            'deferred' => self::deferredItems($preferVehicleId, $currentRepairOrder),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function deferredItems(?int $vehicleId, ?RepairOrder $currentRepairOrder): array
    {
        if ($vehicleId === null || $vehicleId < 1) {
            return [];
        }

        $excludeRepairOrderId = $currentRepairOrder?->id;

        $openRecommendations = Recommendation::query()
            ->with(['estimateLinks', 'originatingConcern.lines', 'originatingRepairOrder'])
            ->where('vehicle_id', $vehicleId)
            ->where('lifecycle', RecommendationLifecycle::Open)
            ->orderByDesc('safety_related')
            ->orderByDesc('id')
            ->get();

        $items = [];
        $coveredConcernIds = [];

        foreach ($openRecommendations as $recommendation) {
            $originatingId = (int) $recommendation->originating_repair_order_concern_id;

            if ($originatingId > 0) {
                $coveredConcernIds[$originatingId] = true;
            }

            if ($excludeRepairOrderId !== null && $recommendation->estimateLinks->contains(
                fn ($link): bool => (int) $link->repair_order_id === $excludeRepairOrderId,
            )) {
                continue;
            }

            $originatingOrder = $recommendation->originatingRepairOrder;
            $amount = (int) ($recommendation->latestEstimatedAmountCents()
                ?? $recommendation->originatingConcern?->lines?->sum('total_cents')
                ?? 0);

            $items[] = [
                'key' => 'rec-'.$recommendation->id,
                'kind' => 'recommendation',
                'title' => $recommendation->title,
                'detail' => self::visitDetail($originatingOrder),
                'amount_label' => self::moneyLabel($amount),
                'add_url' => $currentRepairOrder !== null
                    ? route('operations.repair-orders.recommendations.add-to-estimate', [
                        $currentRepairOrder,
                        $recommendation,
                    ])
                    : '',
            ];
        }

        $deferredConcerns = RepairOrderConcern::query()
            ->with(['repairOrder', 'lines'])
            ->where('disposition', RepairOrderConcernDisposition::Deferred)
            ->whereHas(
                'repairOrder',
                function ($query) use ($vehicleId, $excludeRepairOrderId): void {
                    $query->where('vehicle_id', $vehicleId);

                    if ($excludeRepairOrderId !== null) {
                        $query->where('id', '!=', $excludeRepairOrderId);
                    }
                },
            )
            ->orderByDesc('id')
            ->get();

        foreach ($deferredConcerns as $concern) {
            if (isset($coveredConcernIds[(int) $concern->id])) {
                continue;
            }

            $amount = (int) $concern->lines->sum(
                fn (RepairOrderLine $line): int => (int) ($line->total_cents ?: $line->subtotal_cents),
            );

            $items[] = [
                'key' => 'concern-'.$concern->id,
                'kind' => 'deferred_concern',
                'title' => $concern->summary !== '' ? $concern->summary : 'Deferred work',
                'detail' => self::visitDetail($concern->repairOrder),
                'amount_label' => self::moneyLabel($amount),
                'add_url' => $currentRepairOrder !== null
                    ? route('operations.repair-orders.deferred-work.add-to-estimate', [
                        $currentRepairOrder,
                        $concern,
                    ])
                    : '',
            ];
        }

        return array_slice($items, 0, 8);
    }

    private static function visitDetail(?RepairOrder $repairOrder): string
    {
        if ($repairOrder === null) {
            return '';
        }

        $number = (int) $repairOrder->repair_order_id;
        $when = $repairOrder->opened_at instanceof Carbon
            ? $repairOrder->opened_at->timezone(config('app.timezone'))->format('M j, Y')
            : '';
        $parts = array_values(array_filter([
            $number > 0 ? 'RO '.$number : null,
            $when !== '' ? $when : null,
        ]));

        return implode(' · ', $parts);
    }

    private static function moneyLabel(int $cents): string
    {
        if ($cents < 1) {
            return '';
        }

        return '$'.number_format($cents / 100, 2);
    }
}
