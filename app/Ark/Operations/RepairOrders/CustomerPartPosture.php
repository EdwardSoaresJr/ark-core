<?php

namespace App\Ark\Operations\RepairOrders;

enum CustomerPartPosture: string
{
    case InHand = 'in_hand';
    case Waiting = 'waiting';

    public function label(): string
    {
        return match ($this) {
            self::InHand => 'Customer has part',
            self::Waiting => 'Waiting on customer',
        };
    }

    public function procurementState(): PartProcurementState
    {
        return match ($this) {
            self::InHand => PartProcurementState::Received,
            self::Waiting => PartProcurementState::AwaitingCustomer,
        };
    }

    public static function fromProcurementState(PartProcurementState $state): ?self
    {
        return match ($state) {
            PartProcurementState::Received => self::InHand,
            PartProcurementState::AwaitingCustomer => self::Waiting,
            default => null,
        };
    }

    public static function tryFromInput(?string $value): ?self
    {
        return filled($value) ? self::tryFrom($value) : null;
    }
}
