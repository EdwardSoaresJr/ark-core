<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;

/**
 * @deprecated Prefer SendAdvisorMessageAction with MessageActionKey::Address.
 */
final class SendShopAddressAction
{
    public function __construct(
        private readonly SendAdvisorMessageAction $send,
    ) {}

    /**
     * @return array{message: ConversationMessage}
     */
    public function execute(
        Customer $customer,
        User $actor,
        ?RepairOrder $repairOrder = null,
        ?Conversation $conversation = null,
    ): array {
        return $this->send->execute(
            $customer,
            $actor,
            MessageActionKey::Address,
            $repairOrder,
            $conversation,
        );
    }
}
