<?php

namespace App\Ark\Operations\Attention;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Observations\OperationalObservation;
use App\Ark\Operations\Observations\OperationalObservationType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;

/**
 * Filters conversation attention observations that are noise on the floor.
 */
final class ConversationAttentionObservationFilter
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
    ) {}

    /**
     * @param  list<OperationalObservation>  $observations
     * @return list<OperationalObservation>
     */
    public function filter(array $observations, ?Conversation $conversation, ?int $customerId): array
    {
        if (! $this->shouldSuppressEstimateViewAttention($conversation, $customerId)) {
            return $observations;
        }

        return array_values(array_filter(
            $observations,
            fn (OperationalObservation $observation): bool => ! in_array($observation->type, [
                OperationalObservationType::EstimateViewed,
                OperationalObservationType::EstimateViewedMultipleTimes,
            ], true),
        ));
    }

    private function shouldSuppressEstimateViewAttention(?Conversation $conversation, ?int $customerId): bool
    {
        $customer = $this->resolveCustomer($conversation, $customerId);

        if ($customer === null) {
            return false;
        }

        return $customer->repairOrders()
            ->whereIn('status', RepairOrderStatus::estimateViewAttentionSuppressedSlugs())
            ->exists();
    }

    private function resolveCustomer(?Conversation $conversation, ?int $customerId): ?Customer
    {
        if ($customerId !== null) {
            return Customer::query()->find($customerId);
        }

        if ($conversation === null) {
            return null;
        }

        $phone = trim((string) $conversation->contact_address);

        if ($phone === '') {
            return null;
        }

        return $this->callContextResolver->resolve($phone)?->customer;
    }
}
