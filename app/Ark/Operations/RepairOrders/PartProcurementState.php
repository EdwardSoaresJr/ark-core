<?php

namespace App\Ark\Operations\RepairOrders;

enum PartProcurementState: string
{
    case None = 'none';
    case Sourcing = 'sourcing';
    case Ordered = 'ordered';
    case Partial = 'partial';
    case Received = 'received';
    case Backordered = 'backordered';
    case Installed = 'installed';
    case Canceled = 'canceled';
    case AwaitingCustomer = 'awaiting_customer';

    public function label(?PartLineSource $partSource = null): string
    {
        if ($partSource === PartLineSource::CustomerSupplied) {
            return match ($this) {
                self::None => 'Confirm customer part',
                self::Received => 'In hand',
                self::AwaitingCustomer => 'Waiting on customer',
                self::Installed => 'Installed',
                self::Canceled => 'Canceled',
                self::Sourcing,
                self::Ordered,
                self::Partial,
                self::Backordered => 'Confirm customer part',
            };
        }

        return match ($this) {
            self::None => 'Needs ordered',
            self::Sourcing => 'Sourcing',
            self::Ordered => 'Ordered',
            self::Partial => 'Partial',
            self::Received => 'Received',
            self::Backordered => 'Backordered',
            self::Installed => 'Installed',
            self::Canceled => 'Canceled',
            self::AwaitingCustomer => 'Waiting on customer',
        };
    }

    public function pressureLabel(?PartLineSource $partSource = null): string
    {
        if ($partSource === PartLineSource::CustomerSupplied) {
            return match ($this) {
                self::None,
                self::Sourcing,
                self::Ordered,
                self::Partial,
                self::Backordered => 'confirm customer part',
                self::Received => 'in hand',
                self::AwaitingCustomer => 'waiting on customer',
                self::Installed => 'installed',
                self::Canceled => 'canceled',
            };
        }

        return match ($this) {
            self::None => 'needs ordered',
            self::Sourcing => 'sourcing',
            self::Ordered => 'ordered',
            self::Partial => 'partial',
            self::Received => 'received',
            self::Backordered => 'backordered',
            self::Installed => 'installed',
            self::Canceled => 'canceled',
            self::AwaitingCustomer => 'waiting on customer',
        };
    }

    public function nextActionLabel(?PartLineSource $partSource = null): string
    {
        if ($partSource === PartLineSource::CustomerSupplied) {
            return match ($this) {
                self::None,
                self::Sourcing,
                self::Ordered,
                self::Partial,
                self::Backordered => 'confirm posture',
                self::AwaitingCustomer => 'customer follow-up',
                self::Received => 'install part',
                self::Installed => 'installed',
                self::Canceled => 'not blocking',
            };
        }

        return match ($this) {
            self::None => 'source / order',
            self::Sourcing => 'confirm vendor',
            self::Ordered => 'track arrival',
            self::Partial => 'finish receiving',
            self::Received => 'install part',
            self::Backordered => 'vendor follow-up',
            self::Installed => 'installed',
            self::Canceled => 'not blocking',
            self::AwaitingCustomer => 'customer follow-up',
        };
    }

    public function isResolved(): bool
    {
        return match ($this) {
            self::Received,
            self::Installed,
            self::Canceled => true,
            self::None,
            self::Sourcing,
            self::Ordered,
            self::Partial,
            self::Backordered,
            self::AwaitingCustomer => false,
        };
    }

    public function isShopProcurementState(): bool
    {
        return match ($this) {
            self::Sourcing,
            self::Ordered,
            self::Partial,
            self::Backordered => true,
            default => false,
        };
    }

    public function eventName(): ?string
    {
        return match ($this) {
            self::Sourcing => 'part_sourcing_started',
            self::Ordered => 'part_ordered',
            self::Received => 'part_received',
            self::Backordered => 'part_backordered',
            self::Installed => 'part_installed',
            self::Canceled => 'part_canceled',
            self::AwaitingCustomer => 'part_awaiting_customer',
            self::None,
            self::Partial => null,
        };
    }
}
