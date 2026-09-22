<?php

namespace App\Ark\Operations\RepairOrders;

final class EstimateCompanionSuggestionFingerprint
{
    public static function for(RepairOrder $repairOrder): string
    {
        $repairOrder->loadMissing(['lines', 'concerns']);

        $concerns = $repairOrder->concerns
            ->sortBy(fn (RepairOrderConcern $concern): int => (int) $concern->id)
            ->map(fn (RepairOrderConcern $concern): string => implode('|', [
                (string) $concern->id,
                trim((string) $concern->summary),
                trim((string) $concern->recommendation),
            ]))
            ->values()
            ->all();

        $lines = $repairOrder->lines
            ->sortBy(fn (RepairOrderLine $line): int => (int) $line->id)
            ->map(function (RepairOrderLine $line): string {
                $type = $line->type instanceof RepairOrderLineType
                    ? $line->type->value
                    : (string) $line->type;

                return implode('|', [
                    (string) $line->id,
                    $type,
                    trim((string) $line->description),
                    trim((string) $line->customer_description),
                ]);
            })
            ->values()
            ->all();

        return hash('sha256', json_encode([$concerns, $lines], JSON_THROW_ON_ERROR));
    }
}
