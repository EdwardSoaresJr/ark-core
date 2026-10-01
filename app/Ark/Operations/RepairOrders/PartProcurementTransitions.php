<?php

namespace App\Ark\Operations\RepairOrders;

final class PartProcurementTransitions
{
    /**
     * @return list<PartProcurementState>
     */
    public static function nextStates(RepairOrderLine $line): array
    {
        return self::nextStatesFor($line->procurementState(), $line->part_source);
    }

    /**
     * @return list<PartProcurementState>
     */
    public static function nextStatesFor(PartProcurementState $state, ?PartLineSource $partSource): array
    {
        if ($partSource === PartLineSource::CustomerSupplied) {
            return self::customerNextStates($state);
        }

        return self::shopNextStates($state);
    }

    /**
     * @return list<PartProcurementState>
     */
    private static function customerNextStates(PartProcurementState $state): array
    {
        return match ($state) {
            PartProcurementState::None,
            PartProcurementState::Sourcing,
            PartProcurementState::Ordered,
            PartProcurementState::Partial,
            PartProcurementState::Backordered => [
                PartProcurementState::Received,
                PartProcurementState::AwaitingCustomer,
                PartProcurementState::Canceled,
            ],
            PartProcurementState::AwaitingCustomer => [
                PartProcurementState::Received,
                PartProcurementState::Canceled,
            ],
            PartProcurementState::Received => [
                PartProcurementState::Installed,
                PartProcurementState::AwaitingCustomer,
            ],
            PartProcurementState::Installed => [],
            PartProcurementState::Canceled => [
                PartProcurementState::Received,
                PartProcurementState::AwaitingCustomer,
            ],
        };
    }

    /**
     * @return list<PartProcurementState>
     */
    private static function shopNextStates(PartProcurementState $state): array
    {
        return match ($state) {
            PartProcurementState::None => [
                PartProcurementState::Sourcing,
                PartProcurementState::Ordered,
                PartProcurementState::Backordered,
                PartProcurementState::Canceled,
            ],
            PartProcurementState::Sourcing => [
                PartProcurementState::Ordered,
                PartProcurementState::Backordered,
                PartProcurementState::Canceled,
            ],
            PartProcurementState::Ordered => [
                PartProcurementState::Partial,
                PartProcurementState::Received,
                PartProcurementState::Backordered,
                PartProcurementState::Canceled,
            ],
            PartProcurementState::Partial => [
                PartProcurementState::Received,
                PartProcurementState::Backordered,
                PartProcurementState::Canceled,
            ],
            PartProcurementState::Backordered => [
                PartProcurementState::Sourcing,
                PartProcurementState::Ordered,
                PartProcurementState::Partial,
                PartProcurementState::Received,
                PartProcurementState::Canceled,
            ],
            PartProcurementState::Received => [
                PartProcurementState::Installed,
                PartProcurementState::Backordered,
            ],
            PartProcurementState::Installed => [],
            PartProcurementState::Canceled => [
                PartProcurementState::Sourcing,
                PartProcurementState::Ordered,
            ],
            PartProcurementState::AwaitingCustomer => [
                PartProcurementState::Sourcing,
                PartProcurementState::Ordered,
                PartProcurementState::Canceled,
            ],
        };
    }
}
