<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Support\Collection;

/**
 * Presentation-only: when to hide a Labor description that duplicates its parent repair.
 * Does not mutate stored descriptions.
 */
final class LaborDescriptionPresentation
{
    public static function normalize(?string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''));
    }

    public static function matchesParent(?string $laborDescription, ?string $parentTitle): bool
    {
        $parent = self::normalize($parentTitle);
        if ($parent === '') {
            return false;
        }

        $labor = self::normalize($laborDescription);

        return $labor === '' || $labor === $parent;
    }

    public static function laborCountInGroup(Collection $lines): int
    {
        return $lines
            ->filter(fn (RepairOrderLine $line): bool => $line->type->isLabor() && $line->shouldDisplayOnEstimateWorksheet())
            ->count();
    }

    public static function shouldSuppressWorksheetDescription(
        RepairOrderLine $line,
        ?string $parentTitle,
        int $laborCountInGroup,
    ): bool {
        if (! $line->type->isLabor()) {
            return false;
        }

        return $laborCountInGroup === 1 && self::matchesParent($line->description, $parentTitle);
    }

    /**
     * Compact labor summary for worksheet / review when description is suppressed.
     */
    public static function compactLaborSummary(RepairOrderLine $line): string
    {
        $hours = self::formatHours((float) $line->quantity);
        $policy = trim((string) ($line->labor_category_name ?? ''));
        if ($policy === '') {
            $policy = 'Shop Default';
        }

        return $hours.' hr · '.$policy;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public static function laborCountInSnapshotLines(array $lines): int
    {
        $count = 0;
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            if (($line['type'] ?? '') === RepairOrderLineType::Labor->value) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public static function shouldSuppressCustomerDescription(array $line, ?string $parentTitle, int $laborCountInGroup): bool
    {
        if (($line['type'] ?? '') !== RepairOrderLineType::Labor->value) {
            return false;
        }

        return $laborCountInGroup === 1
            && self::matchesParent((string) ($line['description'] ?? ''), $parentTitle);
    }

    public static function formatHours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.') ?: '0';
    }
}
