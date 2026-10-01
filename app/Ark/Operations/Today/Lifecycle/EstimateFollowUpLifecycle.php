<?php

namespace App\Ark\Operations\Today\Lifecycle;

use App\Ark\Operations\Briefing\BriefingConfidence;
use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Briefing\BriefingEvidenceResolver;
use App\Ark\Operations\Briefing\BriefingItem;
use App\Ark\Operations\Briefing\BriefingPriority;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Today\Surface\TodayLens;
use App\Ark\Operations\Today\Surface\TodayOwnerResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EstimateFollowUpLifecycle implements TodayRecommendationLifecycle
{
    public function __construct(
        private readonly BriefingEvidenceResolver $evidence,
        private readonly EstimateTotalsCalculator $totals,
        private readonly TodayOwnerResolver $owners,
    ) {}

    public function kind(): TodayRecommendationKind
    {
        return TodayRecommendationKind::EstimateFollowUp;
    }

    public function completionAuthority(): TodayCompletionAuthority
    {
        return TodayCompletionAuthority::Communication;
    }

    public function completionEvents(): array
    {
        return [
            TodayCompletionEvent::FollowUpLogged,
            TodayCompletionEvent::CustomerReplied,
            TodayCompletionEvent::EstimateApproved,
            TodayCompletionEvent::EstimateDeclined,
            TodayCompletionEvent::ReminderScheduled,
        ];
    }

    public function candidates(BriefingContext $context, TodayLens $lens): array
    {
        $ownerLabel = match ($lens) {
            TodayLens::Owner => $this->owners->defaultAdvisorLabel(),
            TodayLens::Advisor => $this->owners->forUser($context->user),
            TodayLens::Technician => $this->owners->defaultAdvisorLabel(),
        };

        $sectionKey = 'customer_approvals';

        $candidates = [];

        foreach ($this->pressuredRepairOrders() as $pressure) {
            $repairOrder = $pressure['repair_order'];
            $customerName = $repairOrder->customer?->display_name ?? 'Customer';
            $firstName = trim((string) ($repairOrder->customer?->first_name ?? '')) ?: explode(' ', $customerName)[0];

            $candidates[] = new TodayLifecycleCandidate(
                kind: $this->kind(),
                instanceKey: $this->kind()->value.'_'.$repairOrder->repair_order_id,
                title: 'Call '.$firstName,
                ownerLabel: $ownerLabel,
                url: route('operations.repair-orders.show', $repairOrder),
                whyYouLabel: $lens === TodayLens::Advisor
                    ? 'You are the assigned advisor.'
                    : 'You own estimate follow-up.',
                expectedOutcome: 'Increase approval likelihood.',
                reason: sprintf('Estimate viewed %d×', $pressure['view_count']),
                effortLabel: null,
                aggregateType: RepairOrder::class,
                aggregateId: (int) $repairOrder->repair_order_id,
                pressureSince: $pressure['last_view_at'],
                sortWeight: $pressure['view_count'] >= 5 ? BriefingPriority::High->weight() : BriefingPriority::Normal->weight(),
                sectionKey: $sectionKey,
            );
        }

        return $candidates;
    }

    /**
     * @return list<BriefingItem>
     */
    public function briefingItems(BriefingContext $context): array
    {
        $items = [];

        foreach ($this->pressuredRepairOrders() as $pressure) {
            $repairOrder = $pressure['repair_order'];
            $views = $pressure['views'];
            $lastViewAt = $pressure['last_view_at'];
            $totalCents = $this->totals->totalsFor($repairOrder)->totalCents();
            $customerName = $repairOrder->customer?->display_name ?? 'Customer';

            $items[] = new BriefingItem(
                key: $this->kind()->value.'_'.$repairOrder->repair_order_id,
                headline: sprintf('%s viewed estimate %d× without follow-up', $customerName, $pressure['view_count']),
                summary: sprintf(
                    'Potential revenue %s · last viewed %s',
                    '$'.number_format($totalCents / 100, 0),
                    $lastViewAt->format('M j g:i A'),
                ),
                priority: $pressure['view_count'] >= 5 ? BriefingPriority::High : BriefingPriority::Normal,
                confidence: new BriefingConfidence(
                    score: min(100, 70 + ($pressure['view_count'] * 5)),
                    reason: 'Multiple estimate views with no outbound follow-up after the last view.',
                    signals: [
                        ['label' => sprintf('%d estimate views recorded', $pressure['view_count']), 'satisfied' => true],
                        ['label' => 'No advisor follow-up after last view', 'satisfied' => true],
                        ['label' => 'Repair order still waiting approval', 'satisfied' => true],
                    ],
                    facts: [
                        ['label' => 'Repair order', 'value' => '#'.$repairOrder->repair_order_id],
                        ['label' => 'Customer', 'value' => $customerName],
                        ['label' => 'Estimate total', 'value' => '$'.number_format($totalCents / 100, 0)],
                    ],
                ),
                evidenceItems: $this->evidence->forCommunicationEvents($views),
                actionUrl: route('operations.repair-orders.show', $repairOrder),
                actionLabel: 'Open estimate review',
                repairOrderId: $repairOrder->repair_order_id,
                customerId: $repairOrder->customer_id,
            );
        }

        return $items;
    }

    public function retirementFromOperationalEvent(OperationalEvent $event): ?RecommendationResolutionRetirement
    {
        if ($event->aggregate_type !== RepairOrder::class) {
            return null;
        }

        $repairOrder = RepairOrder::query()
            ->with(['customer', 'communicationEvents'])
            ->whereKey($event->aggregate_id)
            ->first();

        if ($repairOrder === null) {
            return null;
        }

        $completionEvent = match ($event->event_name) {
            OperationalEventName::OperationalCommunicationLogged->value => $this->completionEventFromCommunication($event),
            OperationalEventName::RepairOrderLifecycleChanged->value => $this->completionEventFromLifecycle($repairOrder, $event),
            default => null,
        };

        if ($completionEvent === null) {
            return null;
        }

        $pressure = $this->pressureBeforeEvent($repairOrder, $event);

        if ($pressure === null) {
            return null;
        }

        if ($this->isActive($repairOrder)) {
            return null;
        }

        $customerName = $repairOrder->customer?->display_name ?? 'Customer';
        $firstName = trim((string) ($repairOrder->customer?->first_name ?? '')) ?: explode(' ', $customerName)[0];

        return new RecommendationResolutionRetirement(
            kind: $this->kind(),
            aggregateType: RepairOrder::class,
            aggregateId: (int) $repairOrder->repair_order_id,
            completedByUserId: $event->actor_user_id,
            completionEvent: $completionEvent,
            outcomeLabel: $this->outcomeLabel($completionEvent),
            titleSnapshot: 'Call '.$firstName,
            pressureSince: $pressure['last_view_at'],
            completedAt: $event->occurred_at ?? now(),
        );
    }

    public function retireReason(TodayCompletionEvent $event): string
    {
        return match ($event) {
            TodayCompletionEvent::FollowUpLogged => 'Advisor logged follow-up in communication authority.',
            TodayCompletionEvent::CustomerReplied => 'Customer replied after viewing the estimate.',
            TodayCompletionEvent::EstimateApproved => 'Customer approved the estimate.',
            TodayCompletionEvent::EstimateDeclined => 'Customer declined or estimate closed lost.',
            TodayCompletionEvent::ReminderScheduled => 'Follow-up reminder scheduled.',
        };
    }

    public function isActive(RepairOrder $repairOrder): bool
    {
        return $this->pressureFor($repairOrder) !== null;
    }

    /**
     * @return list<array{repair_order: RepairOrder, views: Collection<int, CommunicationEvent>, view_count: int, last_view_at: Carbon}>
     */
    private function pressuredRepairOrders(): array
    {
        $pressured = [];

        $repairOrders = RepairOrder::query()
            ->with(['customer', 'communicationEvents'])
            ->where('status', RepairOrderStatus::WaitingApproval)
            ->get();

        foreach ($repairOrders as $repairOrder) {
            $pressure = $this->pressureFor($repairOrder);

            if ($pressure === null) {
                continue;
            }

            $pressured[] = $pressure;
        }

        return $pressured;
    }

    /**
     * @return array{repair_order: RepairOrder, views: Collection<int, CommunicationEvent>, view_count: int, last_view_at: Carbon}|null
     */
    private function pressureFor(RepairOrder $repairOrder): ?array
    {
        if (! $repairOrder->status->is(RepairOrderStatus::WaitingApproval)) {
            return null;
        }

        $minViews = (int) config('briefing.estimate_follow_up_min_views', 3);

        $views = $repairOrder->communicationEvents
            ->filter(fn (CommunicationEvent $event): bool => $event->event_type === OperationalCommunicationType::EstimateViewed)
            ->sortBy('occurred_at')
            ->values();

        if ($views->count() < $minViews) {
            return null;
        }

        $lastView = $views->last();
        $lastViewAt = $lastView?->occurred_at ?? $lastView?->created_at;

        if ($lastViewAt === null) {
            return null;
        }

        if ($this->followUpRecordedAfter($repairOrder, $lastViewAt)) {
            return null;
        }

        return [
            'repair_order' => $repairOrder,
            'views' => $views,
            'view_count' => $views->count(),
            'last_view_at' => $lastViewAt,
        ];
    }

    private function followUpRecordedAfter(RepairOrder $repairOrder, Carbon $lastViewAt): bool
    {
        return $repairOrder->communicationEvents->contains(
            fn (CommunicationEvent $event): bool => ($event->occurred_at ?? $event->created_at)?->gt($lastViewAt)
                && $this->communicationEventCompletesFollowUp($event),
        );
    }

    private function communicationEventCompletesFollowUp(CommunicationEvent $event): bool
    {
        if ($event->direction === OperationalCommunicationDirection::Outbound) {
            return in_array($event->event_type, [
                OperationalCommunicationType::EstimateSent,
                OperationalCommunicationType::ApprovalFollowUp,
            ], true);
        }

        return $event->event_type === OperationalCommunicationType::CustomerReply;
    }

    private function completionEventFromCommunication(OperationalEvent $event): ?TodayCompletionEvent
    {
        $payload = $event->payload_json ?? [];
        $type = (string) ($payload['communication_type'] ?? '');

        if ($type === OperationalCommunicationType::CustomerReply->value) {
            return TodayCompletionEvent::CustomerReplied;
        }

        if (in_array($type, [
            OperationalCommunicationType::ApprovalFollowUp->value,
            OperationalCommunicationType::EstimateSent->value,
        ], true)) {
            return TodayCompletionEvent::FollowUpLogged;
        }

        return null;
    }

    private function completionEventFromLifecycle(RepairOrder $repairOrder, OperationalEvent $event): ?TodayCompletionEvent
    {
        $payload = $event->payload_json ?? [];
        $fromStatus = (string) ($payload['from_status'] ?? '');
        $toStatus = (string) ($payload['to_status'] ?? '');

        if ($fromStatus !== RepairOrderStatus::WaitingApproval->value) {
            return null;
        }

        if ($toStatus === RepairOrderStatus::Approved->value) {
            return TodayCompletionEvent::EstimateApproved;
        }

        if ($toStatus === RepairOrderStatus::Closed->value && filled($payload['lost_reason_key'] ?? null)) {
            return TodayCompletionEvent::EstimateDeclined;
        }

        if (! in_array($toStatus, [RepairOrderStatus::WaitingApproval->value], true)) {
            return TodayCompletionEvent::EstimateApproved;
        }

        return null;
    }

    /**
     * @return array{repair_order: RepairOrder, views: Collection<int, CommunicationEvent>, view_count: int, last_view_at: Carbon}|null
     */
    private function pressureBeforeEvent(RepairOrder $repairOrder, OperationalEvent $event): ?array
    {
        $asOf = $event->occurred_at ?? now();
        $payload = $event->payload_json ?? [];

        if ($event->event_name === OperationalEventName::RepairOrderLifecycleChanged->value) {
            if (($payload['from_status'] ?? '') !== RepairOrderStatus::WaitingApproval->value) {
                return null;
            }
        } elseif (! $repairOrder->status->is(RepairOrderStatus::WaitingApproval)) {
            return null;
        }

        $minViews = (int) config('briefing.estimate_follow_up_min_views', 3);

        $views = $repairOrder->communicationEvents
            ->filter(function (CommunicationEvent $communicationEvent) use ($asOf): bool {
                $occurredAt = $communicationEvent->occurred_at ?? $communicationEvent->created_at;

                return $communicationEvent->event_type === OperationalCommunicationType::EstimateViewed
                    && $occurredAt !== null
                    && $occurredAt->lte($asOf);
            })
            ->sortBy('occurred_at')
            ->values();

        if ($views->count() < $minViews) {
            return null;
        }

        $lastView = $views->last();
        $lastViewAt = $lastView?->occurred_at ?? $lastView?->created_at;

        if ($lastViewAt === null) {
            return null;
        }

        $hadPriorFollowUp = $repairOrder->communicationEvents->contains(
            function (CommunicationEvent $communicationEvent) use ($lastViewAt, $asOf): bool {
                $occurredAt = $communicationEvent->occurred_at ?? $communicationEvent->created_at;

                return $occurredAt !== null
                    && $occurredAt->gt($lastViewAt)
                    && $occurredAt->lt($asOf)
                    && $this->communicationEventCompletesFollowUp($communicationEvent);
            },
        );

        if ($hadPriorFollowUp) {
            return null;
        }

        return [
            'repair_order' => $repairOrder,
            'views' => $views,
            'view_count' => $views->count(),
            'last_view_at' => $lastViewAt,
        ];
    }

    private function outcomeLabel(TodayCompletionEvent $event): string
    {
        return match ($event) {
            TodayCompletionEvent::FollowUpLogged => 'Follow-up recorded',
            TodayCompletionEvent::CustomerReplied => 'Customer replied',
            TodayCompletionEvent::EstimateApproved => 'Customer approved estimate',
            TodayCompletionEvent::EstimateDeclined => 'Estimate declined or closed lost',
            TodayCompletionEvent::ReminderScheduled => 'Reminder scheduled',
        };
    }
}
