<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Portal\CreatePortalShortLinkAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use RuntimeException;

class SendPaymentLinkAction
{
    public function __construct(
        private readonly PaymentPortalLinkContext $paymentLink,
        private readonly CreatePortalShortLinkAction $shortLinks,
        private readonly SendOutboundMessageAction $sender,
    ) {}

    /**
     * @return array{message: \App\Ark\Operations\Conversations\ConversationMessage, url: string, balance_due_display: string}
     */
    public function execute(RepairOrder $repairOrder, User $actor, ?Conversation $conversation = null): array
    {
        $repairOrder->loadMissing('customer');

        if ($repairOrder->customer === null) {
            throw new RuntimeException('Repair order does not have a customer.');
        }

        if (! filled($repairOrder->customer->phone)) {
            throw new RuntimeException('Customer does not have a phone number on file.');
        }

        $context = $this->paymentLink->forRepairOrder($repairOrder);
        $shortUrl = $this->shortLinks->execute(
            $context['url'],
            $context['token']->token->expires_at,
        );

        $body = PortalSmsLinkBody::payment($context['balance_due_display'], $shortUrl);

        $result = $this->sender->execute(
            customer: $repairOrder->customer,
            actor: $actor,
            body: $body,
            repairOrder: $repairOrder,
            conversation: $conversation,
        );

        return [
            'message' => $result['message'],
            'url' => $context['url'],
            'balance_due_display' => $context['balance_due_display'],
        ];
    }
}
