<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;

final class RecordPortalEstimateViewAction
{
    public function __construct(
        private readonly CommunicationEventRecorder $communicationEvents,
        private readonly ConversationRecorder $conversations,
        private readonly PortalCustomerActivityBroadcaster $portalInterrupts,
    ) {}

    public function execute(RepairOrder $repairOrder, EstimateAccessToken $accessToken): void
    {
        if ($accessToken->last_viewed_at !== null) {
            return;
        }

        $summary = 'Customer opened the estimate portal link.';

        $message = $this->conversations->recordPortalEstimateView($repairOrder, $summary);

        $this->communicationEvents->record(
            $repairOrder,
            OperationalCommunicationType::EstimateViewed,
            OperationalCommunicationChannel::Website,
            OperationalCommunicationDirection::Inbound,
            $summary,
            message: $message,
        );

        $this->portalInterrupts->broadcastEstimateView($repairOrder, $message);

        app(\App\Ark\Mobile\Push\NotifyMobileLifecyclePushAction::class)->forEstimateViewed($repairOrder);
    }
}
