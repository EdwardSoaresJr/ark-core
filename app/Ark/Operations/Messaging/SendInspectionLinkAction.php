<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\Portal\CreateOrReuseInspectionAccessTokenAction;
use App\Ark\Operations\Portal\CreatePortalShortLinkAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use RuntimeException;

class SendInspectionLinkAction
{
    public function __construct(
        private readonly CreateOrReuseInspectionAccessTokenAction $tokens,
        private readonly CreatePortalShortLinkAction $shortLinks,
        private readonly SendOutboundMessageAction $sender,
    ) {}

    /**
     * @return array{message: \App\Ark\Operations\Conversations\ConversationMessage, url: string, token_reused: bool}
     */
    public function execute(RepairOrder $repairOrder, User $actor): array
    {
        $repairOrder->loadMissing('customer');

        if ($repairOrder->customer === null) {
            throw new RuntimeException('Repair order does not have a customer.');
        }

        if (! filled($repairOrder->customer->phone)) {
            throw new RuntimeException('Customer does not have a phone number on file.');
        }

        if ($repairOrder->isTerminal()) {
            throw new RuntimeException('Closed repair orders cannot send inspection links.');
        }

        if (InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder) === 0) {
            throw new RuntimeException('Record at least one inspection finding before sharing.');
        }

        $accessToken = $this->tokens->execute($repairOrder, $actor);
        $url = route('portal.inspections.show', ['token' => $accessToken->plainToken]);
        $shortUrl = $this->shortLinks->execute(
            $url,
            $accessToken->token->expires_at,
        );

        $body = PortalSmsLinkBody::inspection($shortUrl);

        $result = $this->sender->execute(
            customer: $repairOrder->customer,
            actor: $actor,
            body: $body,
            repairOrder: $repairOrder,
        );

        return [
            'message' => $result['message'],
            'url' => $url,
            'token_reused' => $accessToken->reused,
        ];
    }
}
