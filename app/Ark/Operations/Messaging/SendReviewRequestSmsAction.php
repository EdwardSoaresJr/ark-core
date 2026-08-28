<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use RuntimeException;

final class SendReviewRequestSmsAction
{
    public function __construct(
        private readonly SendOutboundMessageAction $sender,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        User $actor,
        ?Conversation $conversation = null,
    ): ConversationMessage {
        $repairOrder->loadMissing('customer');

        if ($repairOrder->customer === null) {
            throw new RuntimeException('Repair order does not have a customer.');
        }

        $reviewUrl = ReviewRequestCopy::reviewUrl();
        $body = ReviewRequestCopy::smsBody($reviewUrl);

        $result = $this->sender->execute(
            customer: $repairOrder->customer,
            actor: $actor,
            body: $body,
            repairOrder: $repairOrder,
            conversation: $conversation,
            metadata: [
                'kind' => ReviewRequestAuthority::METADATA_KIND,
                'review_url' => $reviewUrl,
                'contact_url' => ReviewRequestCopy::contactUrl(),
            ],
        );

        return $result['message'];
    }
}
