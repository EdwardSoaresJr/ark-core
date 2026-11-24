<?php

namespace App\Ark\Operations\RepairOrders;

/**
 * Readable concern / job copy for boards and lists.
 *
 * Prefer the denormalized repair_orders.concern_summary when present. Many imported
 * or concern-driven ROs leave that column empty while repair_order_concerns.summary
 * holds the real customer/job text — fall back to those rows in position order.
 */
final class RepairOrderConcernText
{
    /**
     * Primary single-string summary for search, triage headlines, and single-line cells.
     */
    public static function primary(RepairOrder $repairOrder): string
    {
        $fromRo = trim((string) ($repairOrder->concern_summary ?? ''));

        if ($fromRo !== '') {
            return $fromRo;
        }

        return self::lines($repairOrder, 1)[0] ?? '';
    }

    /**
     * Up to $max non-empty concern summaries in position order (for compact multi-line cells).
     *
     * @return list<string>
     */
    public static function lines(RepairOrder $repairOrder, int $max = 2): array
    {
        $fromRo = trim((string) ($repairOrder->concern_summary ?? ''));

        if ($fromRo !== '') {
            return [$fromRo];
        }

        if (! $repairOrder->relationLoaded('concerns')) {
            return [];
        }

        return $repairOrder->concerns
            ->sortBy(fn ($concern): int => (int) ($concern->position ?? 0))
            ->map(fn ($concern): string => trim((string) ($concern->summary ?? '')))
            ->filter()
            ->take(max(1, $max))
            ->values()
            ->all();
    }
}
