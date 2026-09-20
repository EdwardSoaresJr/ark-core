<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use Illuminate\Support\Str;

final class OilChangeStickerOilTypeResolver
{
    public static function inferLine(RepairOrder $repairOrder): ?string
    {
        $repairOrder->loadMissing(['lines', 'vehicle']);

        $chunks = [];
        $engine = trim((string) ($repairOrder->vehicle?->engine_display ?? $repairOrder->vehicle?->engine ?? ''));
        if ($engine !== '') {
            $chunks[] = $engine;
        }

        foreach ($repairOrder->lines as $line) {
            if (! in_array($line->type, [
                RepairOrderLineType::Part,
                RepairOrderLineType::Labor,
                RepairOrderLineType::Sublet,
                RepairOrderLineType::Fee,
            ], true)) {
                continue;
            }

            $description = trim((string) $line->description);
            if ($description !== '') {
                $chunks[] = $description;
            }
        }

        $blob = implode("\n", $chunks);
        if ($blob === '') {
            return null;
        }

        $lower = Str::lower($blob);
        if (! str_contains($lower, 'oil')) {
            return null;
        }

        if (preg_match('/\b\d{1,2}w-\d{2}\b/i', $blob, $match)) {
            return strtoupper($match[0]);
        }

        return Str::limit($blob, 64);
    }
}
