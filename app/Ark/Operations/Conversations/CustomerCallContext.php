<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Customers\Customer;
use Illuminate\Support\Collection;

readonly class CustomerCallContext
{
    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @param  Collection<int, CustomerCallContextOpenRepairOrder>  $openRepairOrders
     * @param  Collection<int, ConversationMessage>  $recentConversationMessages
     */
    public function __construct(
        public string $normalizedPhone,
        public string $displayPhone,
        public ?Customer $customer,
        public Collection $vehicles,
        public Collection $openRepairOrders,
        public Collection $recentConversationMessages,
    ) {}

    public function hasMatch(): bool
    {
        return $this->customer !== null;
    }
}
