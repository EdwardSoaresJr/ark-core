<?php

namespace App\Ark\Orientation;

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Illuminate\Support\Carbon;

/**
 * Derives repair-order orientation from authoritative truth only.
 *
 * Not editable. Not authority. Consumed by every interruption surface.
 */
final class RepairOrderOrientationEngine
{
    public function derive(RepairOrder $repairOrder): OperationalOrientation
    {
        $repairOrder->loadMissing([
            'concerns',
            'communicationEvents',
            'assignedTechnician',
            'inspection.items.photos',
        ]);

        $ownerSignal = $this->ownerSignal($repairOrder);

        return new OperationalOrientation(
            situation: $this->situation($repairOrder),
            progressStoppedBecause: $this->progressStoppedBecause($repairOrder),
            owner: $this->ownerLabel($ownerSignal),
            ownerSignal: $ownerSignal,
            pressureLabel: $this->pressureLabel($repairOrder),
            suggestedFollowUpLines: $this->suggestedFollowUpLines($repairOrder),
            confidenceItems: $this->confidenceItems($repairOrder),
        );
    }

    private function pressureLabel(RepairOrder $repairOrder): string
    {
        if ($this->partsBlocked($repairOrder)) {
            return 'Waiting on Parts';
        }

        if ($repairOrder->status->is(RepairOrderStatus::WaitingApproval)) {
            return $this->isWarrantyVisit($repairOrder)
                ? 'Waiting on Warranty'
                : 'Waiting on Customer Approval';
        }

        return $this->situation($repairOrder);
    }

    private function situation(RepairOrder $repairOrder): string
    {
        if ($repairOrder->isTerminal()) {
            return 'Delivered';
        }

        $status = $repairOrder->status->enum();

        if (in_array($status, [RepairOrderStatus::ReadyPickup, RepairOrderStatus::Invoiced], true)) {
            return 'Ready for Pickup';
        }

        if ($status === RepairOrderStatus::WaitingParts || $this->partsBlocked($repairOrder)) {
            return 'Waiting on Parts';
        }

        if ($status === RepairOrderStatus::WaitingApproval) {
            return $this->isWarrantyVisit($repairOrder)
                ? 'Waiting on Warranty'
                : 'Waiting on Customer Approval';
        }

        if (in_array($status, [RepairOrderStatus::Draft, RepairOrderStatus::Estimate], true)) {
            return 'Waiting on Diagnosis';
        }

        if (in_array($status, [
            RepairOrderStatus::Approved,
            RepairOrderStatus::ReadyForWork,
            RepairOrderStatus::InProgress,
            RepairOrderStatus::QualityCheck,
        ], true)) {
            return 'Repair In Progress';
        }

        if ($status === RepairOrderStatus::Completed) {
            return 'Ready for Pickup';
        }

        return $repairOrder->executionPostureLabel();
    }

    private function progressStoppedBecause(RepairOrder $repairOrder): string
    {
        if ($repairOrder->isTerminal()) {
            return 'This repair order is closed and no longer on the active floor.';
        }

        if ($this->partsBlocked($repairOrder)) {
            $partsLine = $repairOrder->partsBlockerSummary()
                ?? $repairOrder->partsPressureSummary()
                ?? 'Approved parts are not ready for production.';

            return $this->sentence($partsLine);
        }

        if ($repairOrder->status->is(RepairOrderStatus::WaitingApproval)) {
            return $this->waitingApprovalProgressStory($repairOrder);
        }

        if (in_array($repairOrder->status->enum(), [RepairOrderStatus::Draft, RepairOrderStatus::Estimate], true)) {
            $concern = trim((string) $repairOrder->concern_summary);

            if ($concern !== '') {
                return 'Customer reported '.$this->sentence($concern).' Diagnosis and estimate are still being built.';
            }

            return 'The vehicle is in the shop but the concern and estimate story is not complete yet.';
        }

        if (in_array($repairOrder->status->enum(), [
            RepairOrderStatus::ReadyPickup,
            RepairOrderStatus::Invoiced,
            RepairOrderStatus::Completed,
        ], true)) {
            if (! $repairOrder->isPaid()) {
                return 'Work is complete and the vehicle is waiting on payment or pickup coordination.';
            }

            return 'Work is complete and the vehicle is ready to leave the shop.';
        }

        if ($repairOrder->assignedTechnician !== null) {
            return 'Approved work is underway with '.$repairOrder->assignedTechnician->name.'.';
        }

        return $this->sentence($repairOrder->executionPostureLabel());
    }

    private function waitingApprovalProgressStory(RepairOrder $repairOrder): string
    {
        if ($this->isWarrantyVisit($repairOrder)) {
            return $this->warrantyProgressStory($repairOrder);
        }

        $estimateSent = $this->latestCommunicationEvent($repairOrder, OperationalCommunicationType::EstimateSent);
        $estimateViewed = $this->latestCommunicationEvent($repairOrder, OperationalCommunicationType::EstimateViewed);
        $customerReply = $this->latestCommunicationEvent($repairOrder, OperationalCommunicationType::CustomerReply);
        $lastCommunication = $repairOrder->latestCommunicationEvent();

        if ($customerReply !== null) {
            $summary = trim((string) $customerReply->summary);

            if ($summary !== '') {
                return $this->sentence($summary);
            }

            return 'Customer replied '.$this->relativeShopLabel($customerReply->occurred_at).' and is still deciding on the estimate.';
        }

        if ($estimateViewed !== null) {
            $viewedLabel = $this->relativeShopLabel($estimateViewed->occurred_at) ?? 'recently';
            $summary = trim((string) ($lastCommunication?->summary ?? ''));

            if ($summary !== '' && $lastCommunication?->event_type === OperationalCommunicationType::EstimateViewed) {
                return 'Customer viewed the estimate '.$viewedLabel.' and '.$this->lowerFirst($summary).'.';
            }

            $recommended = $repairOrder->concerns
                ->where('disposition', RepairOrderConcernDisposition::Recommended);

            if ($recommended->isNotEmpty()) {
                return 'Customer viewed the estimate '.$viewedLabel.' after inspection confirmed '.$this->concernSummaries($recommended).'.';
            }

            return 'Customer viewed the estimate '.$viewedLabel.' and has not approved or replied yet.';
        }

        if ($estimateSent !== null) {
            return 'Estimate was sent '.$this->relativeShopLabel($estimateSent->occurred_at).' but the customer has not viewed it yet.';
        }

        $recommended = $repairOrder->concerns
            ->where('disposition', RepairOrderConcernDisposition::Recommended);

        if ($recommended->isNotEmpty()) {
            return 'Inspection confirmed '.$this->concernSummaries($recommended).' but the estimate has not been sent yet.';
        }

        $concern = trim((string) $repairOrder->concern_summary);

        if ($concern !== '') {
            return 'Customer reported '.$this->sentence($concern).' The estimate has not been sent yet.';
        }

        return 'The estimate has not been sent to the customer yet.';
    }

    private function warrantyProgressStory(RepairOrder $repairOrder): string
    {
        $lastCommunication = $repairOrder->latestCommunicationEvent();
        $summary = trim((string) ($lastCommunication?->summary ?? ''));

        if ($summary !== '') {
            return $this->sentence($summary);
        }

        if ($lastCommunication !== null) {
            $ageHours = $this->ageInHours($lastCommunication->occurred_at);

            if ($ageHours !== null && $ageHours >= 48) {
                return 'Warranty has not responded in '.$this->durationHoursLabel($ageHours).'.';
            }

            return 'Waiting on warranty authorization after '.$lastCommunication->event_type->label().' '.$this->relativeShopLabel($lastCommunication->occurred_at).'.';
        }

        return 'This is a warranty visit and authorization is still outstanding.';
    }

    /**
     * @return list<string>
     */
    private function suggestedFollowUpLines(RepairOrder $repairOrder): array
    {
        if ($repairOrder->isTerminal()) {
            return ['No follow-up needed on closed work.'];
        }

        if ($this->partsBlocked($repairOrder)) {
            return [
                'Order or receive the blocked parts before releasing work to the bay.',
                $this->sentence($repairOrder->executionNextAction()),
            ];
        }

        if ($repairOrder->status->is(RepairOrderStatus::WaitingApproval)) {
            return $this->waitingApprovalFollowUp($repairOrder);
        }

        if (in_array($repairOrder->status->enum(), [RepairOrderStatus::Draft, RepairOrderStatus::Estimate], true)) {
            return [
                'Finish diagnosis and build the estimate before asking for authorization.',
                $this->sentence($repairOrder->executionNextAction()),
            ];
        }

        if (in_array($repairOrder->status->enum(), [
            RepairOrderStatus::ReadyPickup,
            RepairOrderStatus::Invoiced,
            RepairOrderStatus::Completed,
        ], true)) {
            $communicationAction = $repairOrder->communicationNextAction();

            if ($communicationAction !== 'No communication action pending') {
                return [$this->sentence($communicationAction)];
            }

            if (! $repairOrder->isPaid()) {
                return [
                    'Confirm pickup timing after payment is collected.',
                    'Collect balance before releasing the vehicle.',
                ];
            }

            return ['Release the vehicle and close the repair order when pickup is complete.'];
        }

        if ($repairOrder->assignedTechnician !== null) {
            return [
                'No advisor action needed unless the customer reaches out.',
                'Check with '.$repairOrder->assignedTechnician->name.' if production stalls.',
            ];
        }

        return [$this->sentence($repairOrder->executionNextAction())];
    }

    /**
     * @return list<string>
     */
    private function waitingApprovalFollowUp(RepairOrder $repairOrder): array
    {
        if ($this->isWarrantyVisit($repairOrder)) {
            $lastCommunication = $repairOrder->latestCommunicationEvent();
            $ageHours = $this->ageInHours($lastCommunication?->occurred_at);

            if ($ageHours !== null && $ageHours >= 48) {
                return [
                    'Warranty has not responded in '.$this->durationHoursLabel($ageHours).'.',
                    'Call the warranty administrator to move authorization forward.',
                ];
            }

            return [
                'No action right now unless warranty asks for more documentation.',
                'Follow up with warranty if authorization stays quiet past 48 hours.',
            ];
        }

        $estimateSent = $this->latestCommunicationEvent($repairOrder, OperationalCommunicationType::EstimateSent);
        $estimateViewed = $this->latestCommunicationEvent($repairOrder, OperationalCommunicationType::EstimateViewed);
        $customerReply = $this->latestCommunicationEvent($repairOrder, OperationalCommunicationType::CustomerReply);

        if ($customerReply !== null) {
            $summary = mb_strtolower(trim((string) $customerReply->summary));

            if (str_contains($summary, 'payday') || str_contains($summary, 'wait') || str_contains($summary, 'time')) {
                return [
                    'No action right now.',
                    'Customer asked for time after replying '.$this->relativeShopLabel($customerReply->occurred_at).'.',
                    $this->followUpAfterCustomerPause($customerReply->occurred_at),
                ];
            }

            return [
                'Review the customer reply before calling back.',
                'Call to answer questions and move authorization forward.',
            ];
        }

        if ($estimateViewed !== null) {
            $viewedLabel = $this->relativeShopLabel($estimateViewed->occurred_at) ?? 'recently';
            $ageHours = $this->ageInHours($estimateViewed->occurred_at) ?? 0;

            if ($ageHours >= 48) {
                return [
                    'Customer looked at the estimate '.$viewedLabel.'.',
                    'Call today to discuss next steps and any financing questions.',
                ];
            }

            if ($ageHours >= 6) {
                return [
                    'No action right now.',
                    'Estimate viewed '.$viewedLabel.'.',
                    $this->followUpAfterCustomerPause($estimateViewed->occurred_at),
                ];
            }

            return [
                'No action right now.',
                'Estimate viewed '.$viewedLabel.'.',
                'Give the customer room today, then follow up tomorrow morning if there is still no response.',
            ];
        }

        if ($estimateSent !== null) {
            return [
                'Customer has not viewed the estimate.',
                'Call to confirm they received it and can review the recommended work.',
            ];
        }

        return [
            'Customer has not received the estimate yet.',
            'Send the estimate or call to walk them through the findings.',
        ];
    }

    /**
     * @return list<string>
     */
    private function confidenceItems(RepairOrder $repairOrder): array
    {
        $items = [];

        if (trim((string) $repairOrder->concern_summary) !== '') {
            $items[] = 'Customer concern captured';
        }

        if ($repairOrder->concerns->where('disposition', RepairOrderConcernDisposition::Recommended)->isNotEmpty()
            || $repairOrder->concerns->contains(fn (RepairOrderConcern $concern): bool => filled($concern->verified_findings))) {
            $items[] = 'Inspection findings recorded';
        }

        if ($this->inspectionHasPhotos($repairOrder->inspection)) {
            $items[] = 'Photos attached';
        }

        if ($this->latestCommunicationEvent($repairOrder, OperationalCommunicationType::EstimateSent) !== null) {
            $items[] = 'Estimate sent';
        }

        if ($this->latestCommunicationEvent($repairOrder, OperationalCommunicationType::EstimateViewed) !== null) {
            $items[] = 'Estimate viewed';
        }

        if ($repairOrder->concerns->where('disposition', RepairOrderConcernDisposition::Approved)->isNotEmpty()) {
            $items[] = 'Work authorized';
        }

        return $items;
    }

    private function ownerLabel(string $signal): string
    {
        return match ($signal) {
            'customer' => 'Customer',
            'warranty' => 'Warranty',
            'technician' => 'Technician',
            'parts' => 'Parts',
            'system' => 'System',
            default => 'Advisor',
        };
    }

    private function ownerSignal(RepairOrder $repairOrder): string
    {
        if ($repairOrder->isTerminal()) {
            return 'system';
        }

        if ($repairOrder->status->is(RepairOrderStatus::WaitingApproval)) {
            return $this->isWarrantyVisit($repairOrder) ? 'warranty' : 'customer';
        }

        if ($this->partsBlocked($repairOrder)) {
            return 'parts';
        }

        if (
            in_array($repairOrder->status->enum(), [
                RepairOrderStatus::InProgress,
                RepairOrderStatus::QualityCheck,
                RepairOrderStatus::ReadyForWork,
            ], true)
            && $repairOrder->assigned_technician_id !== null
        ) {
            return 'technician';
        }

        if (
            in_array($repairOrder->status->enum(), [
                RepairOrderStatus::ReadyPickup,
                RepairOrderStatus::Invoiced,
                RepairOrderStatus::Completed,
            ], true)
            && ! $repairOrder->isPaid()
        ) {
            return 'customer';
        }

        return 'advisor';
    }

    private function isWarrantyVisit(RepairOrder $repairOrder): bool
    {
        if ($repairOrder->warranty) {
            return true;
        }

        return $repairOrder->concerns->contains(
            fn (RepairOrderConcern $concern): bool => $concern->billing_posture?->isWarranty() ?? false,
        );
    }

    private function partsBlocked(RepairOrder $repairOrder): bool
    {
        if ($repairOrder->status->is(RepairOrderStatus::WaitingParts)) {
            return true;
        }

        return $repairOrder->hasUnresolvedApprovedParts()
            && ! $repairOrder->status->is(RepairOrderStatus::InProgress);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, RepairOrderConcern>  $concerns
     */
    private function concernSummaries($concerns): string
    {
        return $concerns
            ->map(fn (RepairOrderConcern $concern): string => mb_strtolower(trim($concern->summary)))
            ->filter(fn (string $summary): bool => $summary !== '')
            ->take(2)
            ->join(' and ');
    }

    private function latestCommunicationEvent(
        RepairOrder $repairOrder,
        OperationalCommunicationType $type,
    ): ?CommunicationEvent {
        return $repairOrder->communicationEvents
            ->first(fn (CommunicationEvent $event): bool => $event->event_type === $type);
    }

    private function inspectionHasPhotos(?Inspection $inspection): bool
    {
        if (! $inspection instanceof Inspection) {
            return false;
        }

        return $inspection->items->contains(fn ($item): bool => $item->photos->isNotEmpty());
    }

    private function followUpAfterCustomerPause(?Carbon $occurredAt): string
    {
        $ageHours = $this->ageInHours($occurredAt) ?? 0;

        if ($ageHours >= 48) {
            return 'Call today — the customer has had time to decide.';
        }

        if ($ageHours >= 12) {
            return 'Follow up tomorrow morning if there is still no response.';
        }

        return 'Give the customer room today, then follow up tomorrow morning if there is still no response.';
    }

    private function relativeShopLabel(?Carbon $instant): ?string
    {
        if ($instant === null) {
            return null;
        }

        $now = ShopDisplayTimezone::now();
        $at = $instant->copy()->utc()->timezone(ShopDisplayTimezone::resolve());

        if ($at->isToday()) {
            $hours = (int) $at->diffInHours($now);

            if ($hours < 1) {
                return 'just now';
            }

            if ($hours === 1) {
                return '1 hour ago';
            }

            return $hours.' hours ago';
        }

        if ($at->isYesterday()) {
            return 'yesterday';
        }

        if ((int) $at->diffInDays($now) === 1) {
            return 'yesterday';
        }

        return ShopDisplayTimezone::formatDate($instant) ?? 'recently';
    }

    private function ageInHours(?Carbon $instant): ?int
    {
        if ($instant === null) {
            return null;
        }

        return (int) $instant->copy()->utc()->diffInHours(now('UTC'));
    }

    private function durationHoursLabel(int $hours): string
    {
        if ($hours < 48) {
            return $hours.' hours';
        }

        $days = (int) floor($hours / 24);

        return $days === 1 ? '1 day' : $days.' days';
    }

    private function lowerFirst(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        return mb_strtolower(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    private function sentence(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        return str_ends_with($text, '.') ? $text : $text.'.';
    }
}
