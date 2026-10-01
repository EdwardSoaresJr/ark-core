<?php

namespace App\Ark\Operations\RepairOrders;

enum RepairOrderVisitMode: string
{
    case WaitingHere = 'waiting_here';
    case DropOff = 'drop_off';
    case NeedsShuttle = 'needs_shuttle';
    case TowIncoming = 'tow_incoming';

    public function label(): string
    {
        return match ($this) {
            self::WaitingHere => 'Waiting',
            self::DropOff => 'Drop Off',
            self::NeedsShuttle => 'Shuttle',
            self::TowIncoming => 'Tow-In',
        };
    }

    public function applyTo(RepairOrder $repairOrder): void
    {
        $repairOrder->forceFill([
            'waiting_here' => $this === self::WaitingHere,
            'drop_off' => $this === self::DropOff,
            'needs_shuttle' => $this === self::NeedsShuttle,
            'tow_incoming' => $this === self::TowIncoming,
        ]);
    }

    public static function fromRepairOrder(RepairOrder $repairOrder): ?self
    {
        return match (true) {
            $repairOrder->tow_incoming => self::TowIncoming,
            $repairOrder->waiting_here => self::WaitingHere,
            $repairOrder->needs_shuttle => self::NeedsShuttle,
            $repairOrder->drop_off => self::DropOff,
            default => null,
        };
    }
}
