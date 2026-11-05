<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use Illuminate\Support\Collection;

/**
 * What a repair order already holds from its parts cart, keyed by the cart line's source key.
 *
 * A line keeps the cart's own quantity, cost and part number from the moment it was imported,
 * so a later pull can tell an unchanged cart line from one the supplier or advisor changed in the
 * cart. The estimate line itself is never compared: advisors edit it after import.
 */
final class PartsCartImportLedger
{
    public const STATUS_NEW = 'new';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_CHANGED = 'changed';

    public function __construct(private readonly EstimateTotalsCalculator $calculator) {}

    /**
     * @return array{quantity: string, part_cost_cents: int, part_number: ?string}
     */
    public function snapshotFor(PartsTechQuoteLine $line): array
    {
        return [
            'quantity' => number_format((float) $line->quantity, 2, '.', ''),
            'part_cost_cents' => $this->calculator->unitPriceCents($line->partCost),
            'part_number' => $line->partNumber,
        ];
    }

    /**
     * @return Collection<string, Collection<int, RepairOrderLine>>
     */
    public function importedLines(RepairOrder $repairOrder): Collection
    {
        return RepairOrderLine::query()
            ->where('repair_order_id', $repairOrder->id)
            ->whereNotNull('parts_cart_source_key')
            ->with('concern:id,summary')
            ->orderBy('id')
            ->get()
            ->groupBy('parts_cart_source_key');
    }

    /**
     * @param  Collection<string, Collection<int, RepairOrderLine>>  $imported
     * @return array{import_status: string, imported_lines: list<array<string, mixed>>}
     */
    public function statusFor(PartsTechQuoteLine $line, Collection $imported): array
    {
        $existing = $imported->get($line->sourceKey);

        if ($existing === null || $existing->isEmpty()) {
            return ['import_status' => self::STATUS_NEW, 'imported_lines' => []];
        }

        $current = $this->snapshotFor($line);
        $changed = $existing->contains(function (RepairOrderLine $imported) use ($current): bool {
            $snapshot = $imported->parts_cart_snapshot ?? [];

            return (string) ($snapshot['quantity'] ?? '') !== $current['quantity']
                || (int) ($snapshot['part_cost_cents'] ?? -1) !== $current['part_cost_cents']
                || ($snapshot['part_number'] ?? null) !== $current['part_number'];
        });

        return [
            'import_status' => $changed ? self::STATUS_CHANGED : self::STATUS_IMPORTED,
            'imported_lines' => $existing
                ->map(fn (RepairOrderLine $imported): array => [
                    'line_id' => $imported->id,
                    'repair_order_concern_id' => $imported->repair_order_concern_id,
                    'concern_summary' => $imported->concern?->summary,
                    'cart_quantity' => $imported->parts_cart_snapshot['quantity'] ?? null,
                    'cart_part_cost' => isset($imported->parts_cart_snapshot['part_cost_cents'])
                        ? number_format(((int) $imported->parts_cart_snapshot['part_cost_cents']) / 100, 2, '.', '')
                        : null,
                ])
                ->values()
                ->all(),
        ];
    }
}
