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
 * cart. A cart line is Changed in cart when no copy on the estimate was imported at its current
 * values. The estimate line itself is never compared: advisors edit it after import.
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
        $differs = fn (RepairOrderLine $imported): bool => $this->snapshotDiffers($imported->parts_cart_snapshot ?? [], $current);

        return [
            'import_status' => $existing->every($differs) ? self::STATUS_CHANGED : self::STATUS_IMPORTED,
            'imported_lines' => $existing
                ->map(fn (RepairOrderLine $imported): array => [
                    'line_id' => $imported->id,
                    'repair_order_concern_id' => $imported->repair_order_concern_id,
                    'concern_summary' => $imported->concern?->summary,
                    'estimate_quantity' => number_format((float) $imported->quantity, 2, '.', ''),
                    'estimate_unit_sell' => '$'.number_format(((int) $imported->unit_price_cents) / 100, 2),
                    'cart_changed' => $differs($imported),
                    'cart_quantity' => $imported->parts_cart_snapshot['quantity'] ?? null,
                    'cart_part_cost' => isset($imported->parts_cart_snapshot['part_cost_cents'])
                        ? number_format(((int) $imported->parts_cart_snapshot['part_cost_cents']) / 100, 2, '.', '')
                        : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array{quantity: string, part_cost_cents: int, part_number: ?string}  $current
     */
    private function snapshotDiffers(array $snapshot, array $current): bool
    {
        return (string) ($snapshot['quantity'] ?? '') !== $current['quantity']
            || (int) ($snapshot['part_cost_cents'] ?? -1) !== $current['part_cost_cents']
            || ($snapshot['part_number'] ?? null) !== $current['part_number'];
    }
}
