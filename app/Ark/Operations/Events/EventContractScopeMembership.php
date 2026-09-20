<?php

namespace App\Ark\Operations\Events;

/**
 * Which scoped streams include each signed event contract (E0b).
 */
final class EventContractScopeMembership
{
    /**
     * @return list<EventStreamScope>
     */
    public function scopesFor(EventContract $contract): array
    {
        return match ($contract) {
            EventContract::PaymentReceived => [
                EventStreamScope::Customer,
                EventStreamScope::RepairOrder,
                EventStreamScope::ShopFeed,
            ],
        };
    }

    public function includes(EventContract $contract, EventStreamScope $scope): bool
    {
        return in_array($scope, $this->scopesFor($contract), true);
    }
}
