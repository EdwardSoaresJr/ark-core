<?php

namespace App\Ark\Operations\RepairOrders;

enum RepairOrderVisitMode: string
{
    case WaitingHere = 'waiting_here';
    case DropOff = 'drop_off';
    case NeedsShuttle = 'needs_shuttle';
    case TowIncoming = 'tow_incoming';
    case OnSite = 'on_site';

    public function label(): string
    {
        return match ($this) {
            self::WaitingHere => 'Waiting',
            self::DropOff => 'Drop Off',
            self::NeedsShuttle => 'Shuttle',
            self::TowIncoming => 'Tow-In',
            self::OnSite => 'On-site',
        };
    }

    /**
     * Short operator-facing explanation for intake / settings.
     */
    public function description(): string
    {
        return match ($this) {
            self::WaitingHere => 'Customer is waiting at the shop.',
            self::DropOff => 'Vehicle left at the shop.',
            self::NeedsShuttle => 'Customer needs a ride.',
            self::TowIncoming => 'Vehicle arriving by tow.',
            self::OnSite => 'Work at the customer location (mobile / roadside).',
        };
    }

    public function applyTo(RepairOrder $repairOrder): void
    {
        $repairOrder->forceFill([
            'waiting_here' => $this === self::WaitingHere,
            'drop_off' => $this === self::DropOff,
            'needs_shuttle' => $this === self::NeedsShuttle,
            'tow_incoming' => $this === self::TowIncoming,
            'on_site' => $this === self::OnSite,
        ]);
    }

    public static function fromRepairOrder(RepairOrder $repairOrder): ?self
    {
        return match (true) {
            (bool) $repairOrder->on_site => self::OnSite,
            $repairOrder->tow_incoming => self::TowIncoming,
            $repairOrder->waiting_here => self::WaitingHere,
            $repairOrder->needs_shuttle => self::NeedsShuttle,
            $repairOrder->drop_off => self::DropOff,
            default => null,
        };
    }
}
