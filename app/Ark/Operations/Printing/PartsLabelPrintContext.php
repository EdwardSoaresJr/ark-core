<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PartsLabelPrintContext
{
    public const YMM_LIMIT = 30;

    public const DESCRIPTION_LIMIT = 36;

    public const PART_NUMBER_LIMIT = 28;

    public function __construct(
        public readonly string $roNumberLine,
        public readonly string $vehicleLine,
        public readonly string $partNumberLine,
        public readonly string $descriptionLine,
        public readonly string $quantityLine,
    ) {}

    public static function fromLine(RepairOrderLine $line, int $copy = 1, int $of = 1): self
    {
        if ($line->type !== RepairOrderLineType::Part) {
            throw new InvalidArgumentException('Parts labels require a part line.');
        }

        $line->loadMissing(['repairOrder.vehicle']);

        $repairOrder = $line->repairOrder;
        if (! $repairOrder instanceof RepairOrder) {
            throw new InvalidArgumentException('Parts labels require a repair order.');
        }

        $of = max(1, $of);
        $copy = max(1, min($copy, $of));

        $vehicle = $repairOrder->vehicle;
        $ymmRaw = trim(implode(' ', array_filter([
            $vehicle?->year,
            $vehicle?->make,
            $vehicle?->model,
        ])));
        $ymm = $ymmRaw !== '' ? Str::limit($ymmRaw, self::YMM_LIMIT) : 'Vehicle';

        $partNumberRaw = trim((string) ($line->part_number ?? ''));
        $partNumber = $partNumberRaw !== ''
            ? Str::limit($partNumberRaw, self::PART_NUMBER_LIMIT)
            : '—';

        $descriptionRaw = trim((string) $line->description);
        $description = $descriptionRaw !== ''
            ? Str::limit($descriptionRaw, self::DESCRIPTION_LIMIT)
            : 'Part';

        return new self(
            roNumberLine: 'RO '.$repairOrder->repairOrderId(),
            vehicleLine: $ymm,
            partNumberLine: $partNumber,
            descriptionLine: $description,
            quantityLine: $of > 1 ? $copy.'/'.$of : 'Qty '.self::formatQuantity($line->quantity),
        );
    }

    public static function stickerCountForQuantity(mixed $quantity): int
    {
        $raw = trim((string) $quantity);
        if ($raw === '' || ! is_numeric($raw)) {
            return 1;
        }

        return max(1, (int) round((float) $raw));
    }

    /**
     * One URL per physical sticker for this part line.
     *
     * @return list<string>
     */
    public static function printUrlsForLine(RepairOrder $repairOrder, RepairOrderLine $line): array
    {
        $of = self::stickerCountForQuantity($line->quantity);
        $urls = [];

        for ($copy = 1; $copy <= $of; $copy++) {
            $urls[] = route('operations.repair-orders.lines.print-parts-label', [
                'repairOrder' => $repairOrder,
                'line' => $line,
                'copy' => $copy,
                'of' => $of,
            ]);
        }

        return $urls;
    }

    private static function formatQuantity(mixed $quantity): string
    {
        $raw = trim((string) $quantity);
        if ($raw === '') {
            return '1';
        }

        if (! is_numeric($raw)) {
            return $raw;
        }

        $asFloat = (float) $raw;
        if (abs($asFloat - round($asFloat)) < 0.00001) {
            return (string) (int) round($asFloat);
        }

        return rtrim(rtrim(number_format($asFloat, 2, '.', ''), '0'), '.');
    }
}
