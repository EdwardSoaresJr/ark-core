<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Database\Eloquent\Model;

class ConversationLinker
{
    public function link(Conversation $conversation, Model $target): ConversationLink
    {
        return ConversationLink::query()->firstOrCreate([
            'conversation_id' => $conversation->id,
            'linkable_type' => $target->getMorphClass(),
            'linkable_id' => $target->getKey(),
        ]);
    }

    public function linkRepairOrderContext(Conversation $conversation, RepairOrder $repairOrder): void
    {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        $this->link($conversation, $repairOrder);

        if ($repairOrder->customer) {
            $this->link($conversation, $repairOrder->customer);
        }

        if ($repairOrder->vehicle) {
            $this->link($conversation, $repairOrder->vehicle);
        }
    }
}
