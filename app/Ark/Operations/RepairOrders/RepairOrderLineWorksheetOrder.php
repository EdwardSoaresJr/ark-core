<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Support\Collection;

/**
 * Stable worksheet order inside a concern / work group.
 *
 * Labor → Package → Part → Sublet → Fee → Note (then id).
 * Display order only — not financial authority.
 */
final class RepairOrderLineWorksheetOrder
{
    public static function rank(RepairOrderLineType|string|null $type): int
    {
        $resolved = $type instanceof RepairOrderLineType
            ? $type
            : RepairOrderLineType::tryFrom((string) $type);

        return match ($resolved) {
            RepairOrderLineType::Labor => 10,
            RepairOrderLineType::Package => 20,
            RepairOrderLineType::Part => 30,
            RepairOrderLineType::Sublet => 40,
            RepairOrderLineType::Fee => 50,
            RepairOrderLineType::Note => 60,
            default => 99,
        };
    }

    /**
     * Qualified CASE expression for Eloquent orderByRaw.
     */
    public static function sqlCaseExpression(string $column = 'repair_order_lines.type'): string
    {
        return "CASE {$column}"
            ." WHEN 'labor' THEN 10"
            ." WHEN 'package' THEN 20"
            ." WHEN 'part' THEN 30"
            ." WHEN 'sublet' THEN 40"
            ." WHEN 'fee' THEN 50"
            ." WHEN 'note' THEN 60"
            .' ELSE 99 END';
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $lines
     * @return Collection<int, RepairOrderLine>
     */
    public static function sort(Collection $lines): Collection
    {
        return $lines
            ->sortBy([
                fn (RepairOrderLine $line): int => self::rank($line->type),
                fn (RepairOrderLine $line): int => (int) $line->id,
            ])
            ->values();
    }
}
