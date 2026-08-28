<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Portal\CreateOrReuseEstimateAccessTokenAction;
use App\Ark\Operations\Portal\CreatePortalShortLinkAction;
use App\Ark\Operations\RepairOrders\MarkEstimateAwaitingCustomerApprovalAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use RuntimeException;

class SendEstimateLinkAction
{
    public function __construct(
        private readonly CreateOrReuseEstimateAccessTokenAction $tokens,
        private readonly CreatePortalShortLinkAction $shortLinks,
        private readonly SendOutboundMessageAction $sender,
        private readonly CommunicationEventRecorder $communicationEvents,
        private readonly MarkEstimateAwaitingCustomerApprovalAction $markAwaitingApproval,
    ) {}

    /**
     * @return array{
     *     message: \App\Ark\Operations\Conversations\ConversationMessage,
     *     url: string,
     *     token_reused: bool,
     *     awaiting_approval: array{
     *         moved: bool,
     *         from_status: string,
     *         to_status: string|null,
     *         reason: string,
     *         blocking_message: string|null,
     *         toast: string|null,
     *     },
     * }
     */
    public function execute(
        RepairOrder $repairOrder,
        User $actor,
        ?\App\Ark\Operations\Conversations\Conversation $conversation = null,
        ?string $recipientPhone = null,
    ): array {
        $repairOrder->loadMissing('customer');

        if ($repairOrder->customer === null) {
            throw new RuntimeException('Repair order does not have a customer.');
        }

        $toPhone = filled($recipientPhone) ? $recipientPhone : $repairOrder->customer->phone;

        if (! filled($toPhone)) {
            throw new RuntimeException('Customer does not have a phone number on file.');
        }

        $accessToken = $this->tokens->execute($repairOrder, $actor);
        $url = route('portal.estimates.show', ['token' => $accessToken->plainToken]);
        $shortUrl = $this->shortLinks->execute(
            $url,
            $accessToken->token->expires_at,
        );

        $body = PortalSmsLinkBody::estimate($shortUrl);

        $result = $this->sender->execute(
            customer: $repairOrder->customer,
            actor: $actor,
            body: $body,
            repairOrder: $repairOrder,
            conversation: $conversation,
            toPhone: $toPhone,
        );

        $this->communicationEvents->record(
            $repairOrder,
            OperationalCommunicationType::EstimateSent,
            OperationalCommunicationChannel::Sms,
            OperationalCommunicationDirection::Outbound,
            'Estimate portal link texted to customer.',
            actor: $actor,
            message: $result['message'],
        );

        $awaitingApproval = $this->markAwaitingApproval->execute($repairOrder->fresh(), $actor);

        return [
            'message' => $result['message'],
            'url' => $url,
            'token_reused' => $accessToken->reused,
            'awaiting_approval' => $awaitingApproval,
        ];
    }
}
