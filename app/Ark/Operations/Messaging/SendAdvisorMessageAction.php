<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use RuntimeException;

/**
 * Advisor Message Action send — intent → ConversationMessage via SMS.
 */
final class SendAdvisorMessageAction
{
    public function __construct(
        private readonly SendOutboundMessageAction $sender,
    ) {}

    /**
     * @return array{message: ConversationMessage}
     */
    public function execute(
        Customer $customer,
        User $actor,
        MessageActionKey $action,
        ?RepairOrder $repairOrder = null,
        ?Conversation $conversation = null,
    ): array {
        if (! in_array($action, MessageActionKey::advisorOneTap(), true)) {
            throw new RuntimeException('This message action cannot be sent from the composer.');
        }

        $body = MessageActionsSettings::body($action);

        $result = $this->sender->execute(
            customer: $customer,
            actor: $actor,
            body: $body,
            repairOrder: $repairOrder,
            conversation: $conversation,
            metadata: MessageActionContract::metadata($action, []),
        );

        return [
            'message' => $result['message'],
        ];
    }
}
