<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Portal\CreatePortalShortLinkAction;
use App\Ark\Operations\Portal\PortalShortLinkPurpose;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use RuntimeException;

final class SendDepositRequestLinkAction
{
    public function __construct(
        private readonly DepositPortalLinkContext $depositLink,
        private readonly CreatePortalShortLinkAction $shortLinks,
        private readonly SendOutboundMessageAction $sender,
        private readonly CommunicationEventRecorder $communicationEvents,
    ) {}

    /**
     * @return array{message: ?ConversationMessage, url: string, amount_display: string}
     */
    public function execute(
        RepairOrder $repairOrder,
        User $actor,
        int $amountCents,
        ?Conversation $conversation = null,
    ): array {
        $repairOrder->loadMissing('customer');

        if ($repairOrder->customer === null) {
            throw new RuntimeException('Repair order does not have a customer.');
        }

        if (! filled($repairOrder->customer->phone)) {
            throw new RuntimeException('Customer does not have a phone number on file.');
        }

        $context = $this->depositLink->forRepairOrder($repairOrder, $amountCents);
        $shortUrl = $this->shortLinks->execute(
            $context['url'],
            $context['token']->token->expires_at,
            $repairOrder,
            PortalShortLinkPurpose::Deposit,
        );

        $body = PortalSmsLinkBody::deposit(
            $context['amount_display'],
            $shortUrl,
            remaining: $repairOrder->balanceDue()->unappliedDepositsCents > 0,
        );

        $result = $this->sender->execute(
            customer: $repairOrder->customer,
            actor: $actor,
            body: $body,
            repairOrder: $repairOrder,
            conversation: $conversation,
        );

        $this->communicationEvents->record(
            $repairOrder,
            OperationalCommunicationType::InvoiceSent,
            OperationalCommunicationChannel::Sms,
            OperationalCommunicationDirection::Outbound,
            'Deposit request texted to customer. Amount '.$context['amount_display'].'.',
            actor: $actor,
            message: $result['message'],
        );

        return [
            'message' => $result['message'],
            'url' => $context['url'],
            'amount_display' => $context['amount_display'],
        ];
    }
}
