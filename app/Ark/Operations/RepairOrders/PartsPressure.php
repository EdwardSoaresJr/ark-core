<?php

namespace App\Ark\Operations\RepairOrders;

enum PartsPressure: string
{
    case NoPartsNeeded = 'no_parts_needed';
    case AllPartsAvailable = 'all_parts_available';
    case WaitingParts = 'waiting_parts';
    case PartialParts = 'partial_parts';
    case Backordered = 'backordered';

    public function label(): string
    {
        return match ($this) {
            self::NoPartsNeeded => 'No Parts Needed',
            self::AllPartsAvailable => 'All Parts Available',
            self::WaitingParts => 'Waiting Parts',
            self::PartialParts => 'Partial Parts',
            self::Backordered => 'Backordered',
        };
    }

    public function showsChip(): bool
    {
        return ! in_array($this, [self::NoPartsNeeded, self::AllPartsAvailable], true);
    }

    public function chipTone(): string
    {
        return match ($this) {
            self::Backordered => 'backordered',
            self::PartialParts => 'partial',
            self::WaitingParts => 'waiting',
            default => 'neutral',
        };
    }
}
