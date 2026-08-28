<?php

namespace App\Ark\Mobile\Push;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Observations\OperationalObservationType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Telephony\CallSession;

/**
 * Lifecycle-aware mobile pushes — advisor copy, not generic alerts.
 */
final class NotifyMobileLifecyclePushAction
{
    public function __construct(
        private readonly MobilePushService $push,
        private readonly MobileAwarePushCopy $copy,
        private readonly MobilePushStaffAudience $audience,
    ) {}

    public function forMissedCall(CallSession $session): int
    {
        $copy = $this->copy->forMissedCall($session);

        return $this->dispatch(new MobilePushMessage(
            title: $copy['title'],
            body: $copy['body'],
            deepLink: 'calls',
            callSessionId: $session->id,
            repairOrderId: $session->repair_order_id,
            tone: OperationalObservationType::IncomingCall->tone(),
        ));
    }

    public function forVoicemail(CallSession $session): int
    {
        $copy = $this->copy->forVoicemail($session);

        return $this->dispatch(new MobilePushMessage(
            title: $copy['title'],
            body: $copy['body'],
            deepLink: 'calls',
            callSessionId: $session->id,
            repairOrderId: $session->repair_order_id,
            tone: 'waiting',
        ));
    }

    public function forEstimateViewed(RepairOrder $repairOrder): int
    {
        $copy = $this->copy->forEstimateViewed($repairOrder);

        return $this->dispatch(new MobilePushMessage(
            title: $copy['title'],
            body: $copy['body'],
            deepLink: 'conversation',
            repairOrderId: $repairOrder->repair_order_id ?? $repairOrder->id,
            tone: OperationalObservationType::EstimateViewed->tone(),
        ));
    }

    public function forEstimateApproved(ApprovalEvent $approval): int
    {
        if (! in_array($approval->approval_type, [ApprovalType::Repair, ApprovalType::Partial], true)) {
            return 0;
        }

        $repairOrder = $approval->visit;
        if (! $repairOrder instanceof RepairOrder) {
            return 0;
        }

        $copy = $this->copy->forEstimateApproved($approval);

        return $this->dispatch(new MobilePushMessage(
            title: $copy['title'],
            body: $copy['body'],
            deepLink: 'conversation',
            repairOrderId: $repairOrder->repair_order_id ?? $repairOrder->id,
            tone: OperationalObservationType::EstimateApproved->tone(),
        ));
    }

    public function forPartsArrived(RepairOrder $repairOrder, ?string $partLabel = null): int
    {
        $copy = $this->copy->forPartsArrived($repairOrder, $partLabel);

        return $this->dispatch(new MobilePushMessage(
            title: $copy['title'],
            body: $copy['body'],
            deepLink: 'repair_order',
            repairOrderId: $repairOrder->repair_order_id ?? $repairOrder->id,
            tone: OperationalObservationType::PartsArrived->tone(),
        ));
    }

    public function forWaitingParts(RepairOrder $repairOrder): int
    {
        $copy = $this->copy->forWaitingParts($repairOrder);

        return $this->dispatch(new MobilePushMessage(
            title: $copy['title'],
            body: $copy['body'],
            deepLink: 'repair_order',
            repairOrderId: $repairOrder->repair_order_id ?? $repairOrder->id,
            tone: OperationalObservationType::RepairOrderWaitingParts->tone(),
        ));
    }

    public function forVehicleReady(RepairOrder $repairOrder): int
    {
        $copy = $this->copy->forVehicleReady($repairOrder);

        return $this->dispatch(new MobilePushMessage(
            title: $copy['title'],
            body: $copy['body'],
            deepLink: 'repair_order',
            repairOrderId: $repairOrder->repair_order_id ?? $repairOrder->id,
            tone: OperationalObservationType::VehicleReady->tone(),
        ));
    }

    private function dispatch(MobilePushMessage $message): int
    {
        if (! $this->push->isEnabled()) {
            return 0;
        }

        return $this->push->sendToUsers($message, $this->audience->advisorsAndAdmins());
    }
}
