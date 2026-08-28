<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\InspectionChecklistItems;
use App\Ark\Operations\Inspections\InspectionFindingIntent;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionItemMeasurement;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use App\Ark\Operations\Timeline\Mappers\OperationalEventEntryMapper;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * RO workspace intelligence — health, next action, recommendations, alerts, operational timeline.
 *
 * Answers: what should this technician or advisor do next?
 */
final class RepairOrderWorkspaceIntelligenceProjection
{
    /** @var list<string> */
    private const TIMELINE_OPERATIONAL_NAMES = [
        OperationalEventName::RepairOrderCreated->value,
        OperationalEventName::RepairOrderLifecycleChanged->value,
        OperationalEventName::RepairOrderTechnicianAssigned->value,
        OperationalEventName::EstimateDocumentGenerated->value,
        OperationalEventName::EstimateDocumentRefreshed->value,
        OperationalEventName::EstimateEmailedToCustomer->value,
        OperationalEventName::ConcernDispositionChanged->value,
        OperationalEventName::ConcernProductionStatusChanged->value,
        OperationalEventName::PortalDocumentViewed->value,
        OperationalEventName::RepairOrderPaymentReceived->value,
    ];

    public function __construct(
        private readonly OperationalEventEntryMapper $operationalEventMapper,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forRepairOrder(RepairOrder $repairOrder, User $viewer, string $profile): array
    {
        $repairOrder->loadMissing([
            'concerns',
            'inspection.items.measurements',
            'inspection.items.photos',
        ]);

        $orderedItems = $repairOrder->inspection !== null
            ? InspectionChecklistItems::orderedChecklistItems($repairOrder->inspection)
            : collect();

        $inspectionProgress = $this->inspectionProgress($orderedItems);
        $recommendationsQueue = $this->recommendationsQueue($orderedItems);
        $alerts = $this->alerts($repairOrder, $profile);
        $timeline = $this->timeline($repairOrder, $orderedItems);
        $lastActivity = $this->lastActivity($repairOrder, $orderedItems, $timeline);
        $health = $this->health(
            $repairOrder,
            $inspectionProgress,
            $recommendationsQueue,
            $lastActivity,
        );
        $next = $this->nextAction(
            $repairOrder,
            $profile,
            $orderedItems,
            $inspectionProgress,
            $recommendationsQueue,
            $alerts,
        );

        return [
            'health' => $health,
            'next' => $next,
            'recommendations_queue' => $recommendationsQueue,
            'alerts' => $alerts,
            'timeline' => $timeline,
            'confidence' => $this->confidence(),
        ];
    }

    /**
     * @param  Collection<int, InspectionItem>  $orderedItems
     * @return array<string, mixed>
     */
    private function inspectionProgress(Collection $orderedItems): array
    {
        $total = $orderedItems->count();
        $complete = $orderedItems
            ->filter(fn (InspectionItem $item): bool => $item->observed_state !== InspectionObservedState::NotChecked)
            ->count();
        $remaining = max(0, $total - $complete);
        $nextItem = $orderedItems
            ->first(fn (InspectionItem $item): bool => $item->observed_state === InspectionObservedState::NotChecked);

        return [
            'total' => $total,
            'complete' => $complete,
            'remaining' => $remaining,
            'progress_fraction' => $total > 0 ? round($complete / $total, 3) : 0.0,
            'next_item' => $nextItem instanceof InspectionItem
                ? [
                    'id' => $nextItem->id,
                    'label' => $nextItem->label,
                    'category_name' => $nextItem->checklist_category_name ?? $nextItem->categoryLabel(),
                ]
                : null,
        ];
    }

    /**
     * @param  Collection<int, InspectionItem>  $orderedItems
     * @return list<array<string, mixed>>
     */
    private function recommendationsQueue(Collection $orderedItems): array
    {
        return $orderedItems
            ->map(function (InspectionItem $item): ?array {
                $status = $item->observed_state instanceof InspectionObservedState
                    ? InspectionChecklistStatus::fromObservedState($item->observed_state)
                    : null;

                if ($status === null || ! in_array($status, [
                    InspectionChecklistStatus::NeedsAttention,
                    InspectionChecklistStatus::Failed,
                ], true)) {
                    return null;
                }

                $measurement = $item->measurements->first();
                $ready = $measurement instanceof InspectionItemMeasurement;

                return [
                    'id' => 'item:'.$item->id,
                    'item_id' => $item->id,
                    'label' => $item->label,
                    'status' => $ready ? 'ready' : 'pending',
                    'status_label' => $ready ? 'Ready for review' : 'Needs measurement',
                    'summary' => $ready
                        ? trim($item->label.' · '.$this->intentLabel($item).' · '.$measurement->formattedValue())
                        : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function alerts(RepairOrder $repairOrder, string $profile): array
    {
        $alerts = [];
        $visitMode = RepairOrderVisitMode::fromRepairOrder($repairOrder);
        $status = RepairOrderStatus::fromSlug((string) $repairOrder->status);

        if ($visitMode === RepairOrderVisitMode::WaitingHere) {
            $alerts[] = $this->alert('customer_waiting', 'Customer waiting', 'warning');
        }

        if (RepairOrderStatus::isWaitingCustomerApproval($status)) {
            $alerts[] = $this->alert('waiting_approval', 'Waiting approval', 'info');
        }

        if ((bool) $repairOrder->warranty) {
            $alerts[] = $this->alert('warranty_pending', 'Warranty claim pending', 'info');
        }

        $estimateViewed = CommunicationEvent::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('event_type', OperationalCommunicationType::EstimateViewed)
            ->exists();

        if ($estimateViewed && $profile === 'advisor') {
            $alerts[] = $this->alert('estimate_viewed', 'Estimate viewed', 'info');
        }

        if ($repairOrder->opened_at !== null && $repairOrder->opened_at->isToday()) {
            $alerts[] = $this->alert('vehicle_promised_today', 'Vehicle in shop today', 'neutral');
        }

        return $alerts;
    }

    /**
     * @param  Collection<int, InspectionItem>  $orderedItems
     * @return list<array<string, mixed>>
     */
    private function timeline(RepairOrder $repairOrder, Collection $orderedItems): array
    {
        $entries = [];

        // Headlines we synthesize below; used to suppress duplicate operational
        // events (e.g. a RepairOrderCreated event that restates "Repair order
        // opened" already shown as the visit entry).
        $syntheticHeadlines = [];

        $openedAt = $repairOrder->opened_at ?? $repairOrder->created_at;
        if ($openedAt instanceof CarbonInterface) {
            $visitMode = RepairOrderVisitMode::fromRepairOrder($repairOrder);
            $openedHeadline = match ($visitMode) {
                RepairOrderVisitMode::WaitingHere => 'Customer waiting in shop',
                RepairOrderVisitMode::DropOff => 'Vehicle dropped off',
                RepairOrderVisitMode::TowIncoming => 'Tow incoming',
                default => 'Repair order opened',
            };
            $syntheticHeadlines[] = mb_strtolower($openedHeadline);
            $syntheticHeadlines[] = 'repair order opened';
            $entries[] = $this->timelineEntry(
                occurredAt: $openedAt,
                headline: $openedHeadline,
                kind: 'visit',
            );
        }

        $inspection = $repairOrder->inspection;
        if ($inspection?->started_at instanceof CarbonInterface) {
            $entries[] = $this->timelineEntry(
                occurredAt: $inspection->started_at,
                headline: 'Inspection started',
                kind: 'inspection',
            );
        }

        foreach ($orderedItems as $item) {
            if ($item->observed_state === InspectionObservedState::NotChecked) {
                continue;
            }

            if (! $item->updated_at instanceof CarbonInterface) {
                continue;
            }

            $entries[] = $this->timelineEntry(
                occurredAt: $item->updated_at,
                headline: $item->label.' completed',
                kind: 'inspection_item',
                body: InspectionChecklistStatus::fromObservedState($item->observed_state)?->label(),
                item_id: $item->id,
            );
        }

        $operationalEvents = OperationalEvent::query()
            ->with('actor:id,name')
            ->where('aggregate_type', RepairOrder::class)
            ->where('aggregate_id', $repairOrder->id)
            ->whereIn('event_name', self::TIMELINE_OPERATIONAL_NAMES)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        foreach ($operationalEvents as $event) {
            $mapped = $this->operationalEventMapper->map($event);
            if ($mapped === null) {
                continue;
            }

            if (in_array(mb_strtolower($mapped->headline), $syntheticHeadlines, true)) {
                continue;
            }

            $entries[] = $this->timelineEntry(
                occurredAt: $mapped->occurredAt,
                headline: $mapped->headline,
                kind: $mapped->kind->value,
                body: $mapped->body,
                actor: $mapped->actor,
            );
        }

        $communicationEvents = CommunicationEvent::query()
            ->where('repair_order_id', $repairOrder->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        foreach ($communicationEvents as $event) {
            $entries[] = $this->timelineEntry(
                occurredAt: $event->occurred_at ?? now(),
                headline: $event->event_type?->label() ?? 'Customer activity',
                kind: 'communication',
                body: filled($event->summary) ? (string) $event->summary : null,
            );
        }

        // take(-30) keeps the last 30 entries but PRESERVES their original keys,
        // so without a trailing ->values() the array serializes as a JSON object
        // ({"5":..,"6":..}) once an RO has >30 timeline entries — which made the
        // mobile workspace fail to parse (List cast on a Map) and the whole RO
        // refused to open. Reindex after taking and mapping.
        return collect($entries)
            ->sortBy(fn (array $entry): int => $entry['occurred_at_ts'])
            ->take(-30)
            ->map(fn (array $entry): array => collect($entry)->except('occurred_at_ts')->all())
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $timeline
     * @param  Collection<int, InspectionItem>  $orderedItems
     * @return array<string, mixed>|null
     */
    private function lastActivity(RepairOrder $repairOrder, Collection $orderedItems, array $timeline): ?array
    {
        $candidates = collect($timeline)
            ->pluck('occurred_at')
            ->filter()
            ->map(fn (string $value): CarbonInterface => \Illuminate\Support\Carbon::parse($value));

        $latestItem = $orderedItems
            ->filter(fn (InspectionItem $item): bool => $item->observed_state !== InspectionObservedState::NotChecked)
            ->sortByDesc(fn (InspectionItem $item) => $item->updated_at)
            ->first();

        if ($latestItem?->updated_at instanceof CarbonInterface) {
            $candidates->push($latestItem->updated_at);
        }

        if ($repairOrder->updated_at instanceof CarbonInterface) {
            $candidates->push($repairOrder->updated_at);
        }

        $latest = $candidates->sortDesc()->first();
        if (! $latest instanceof CarbonInterface) {
            return null;
        }

        return [
            'occurred_at' => $latest->toIso8601String(),
            'label' => 'Last activity '.$latest->diffForHumans(short: true),
        ];
    }

    /**
     * @param  array<string, mixed>  $inspectionProgress
     * @param  list<array<string, mixed>>  $recommendationsQueue
     * @param  array<string, mixed>|null  $lastActivity
     * @return array<string, mixed>
     */
    private function health(
        RepairOrder $repairOrder,
        array $inspectionProgress,
        array $recommendationsQueue,
        ?array $lastActivity,
    ): array {
        $readyRecommendations = collect($recommendationsQueue)
            ->where('status', 'ready')
            ->count();

        $visitMode = RepairOrderVisitMode::fromRepairOrder($repairOrder);

        return [
            'concern_count' => $repairOrder->concerns->count(),
            'inspection' => $inspectionProgress,
            'recommendations_ready_count' => $readyRecommendations,
            'recommendations_pending_count' => collect($recommendationsQueue)
                ->where('status', 'pending')
                ->count(),
            'lifecycle_label' => $repairOrder->statusDisplayLabel(),
            'lifecycle_tone' => MobileRepairOrderStatusTone::forStatus($repairOrder->status),
            'customer_posture_label' => match ($visitMode) {
                RepairOrderVisitMode::WaitingHere => 'Customer waiting',
                RepairOrderVisitMode::DropOff => 'Drop off',
                default => null,
            },
            'last_activity' => $lastActivity,
        ];
    }

    /**
     * @param  Collection<int, InspectionItem>  $orderedItems
     * @param  array<string, mixed>  $inspectionProgress
     * @param  list<array<string, mixed>>  $recommendationsQueue
     * @param  list<array<string, mixed>>  $alerts
     * @return array<string, mixed>|null
     */
    private function nextAction(
        RepairOrder $repairOrder,
        string $profile,
        Collection $orderedItems,
        array $inspectionProgress,
        array $recommendationsQueue,
        array $alerts,
    ): ?array {
        $status = RepairOrderStatus::fromSlug((string) $repairOrder->status);
        $customerWaiting = collect($alerts)->contains(fn (array $alert): bool => ($alert['key'] ?? '') === 'customer_waiting');

        if ($profile !== 'technician' && $status->isIntake()) {
            return $this->nextPayload(
                label: 'Review estimate scope',
                reason: 'Estimate is still being built on this RO',
                actionKey: 'review_estimate',
                section: 'concerns',
                decision: [
                    'question' => 'Why is NEXT suggesting estimate review?',
                    'rule' => 'EstimatePhaseAdvisorReview',
                    'factors' => [
                        ['label' => 'Repair order in draft or estimate', 'matched' => true],
                        ['label' => 'Advisor workspace — not production inspection', 'matched' => true],
                    ],
                ],
            );
        }

        if ($profile === 'technician' && $inspectionProgress['remaining'] > 0) {
            $resolution = $this->resolveNextInspectionItem($orderedItems, $customerWaiting);
            $nextItem = $resolution['item'];

            if ($nextItem instanceof InspectionItem) {
                $requiresPhoto = (bool) ($nextItem->photos->isEmpty()
                    && $nextItem->observed_state !== InspectionObservedState::NotChecked
                    && $nextItem->observed_state?->requiresEvidencePrompt());

                if ($requiresPhoto) {
                    return $this->nextPayload(
                        label: 'Capture photo for '.$nextItem->label,
                        reason: 'Evidence required on flagged item',
                        actionKey: 'capture_photo',
                        section: 'inspection',
                        itemId: $nextItem->id,
                        decision: $this->photoRequiredDecision($nextItem),
                    );
                }

                return $this->nextPayload(
                    label: 'Check '.$nextItem->label,
                    reason: $customerWaiting
                        ? 'Customer waiting — keep inspection moving'
                        : 'Next unchecked inspection item',
                    actionKey: 'inspect_item',
                    section: 'inspection',
                    itemId: $nextItem->id,
                    decision: $this->inspectItemDecision(
                        $nextItem,
                        $orderedItems,
                        $customerWaiting,
                        $resolution['used_safety_priority'],
                    ),
                );
            }
        }

        $readyRecommendation = collect($recommendationsQueue)->firstWhere('status', 'ready');
        if ($readyRecommendation !== null) {
            return $this->nextPayload(
                label: 'Review '.$readyRecommendation['label'],
                reason: 'Recommendation ready for advisor review',
                actionKey: 'review_recommendation',
                section: 'overview',
                itemId: (int) $readyRecommendation['item_id'],
                decision: [
                    'question' => 'Why is NEXT suggesting '.$readyRecommendation['label'].'?',
                    'rule' => 'RecommendationReadyForReview',
                    'factors' => [
                        ['label' => 'Measurement captured', 'matched' => true],
                        ['label' => 'Recommendation draft available', 'matched' => true],
                        ['label' => 'Advisor review required', 'matched' => true],
                    ],
                ],
            );
        }

        if ($profile === 'advisor' && RepairOrderStatus::isWaitingCustomerApproval(RepairOrderStatus::fromSlug((string) $repairOrder->status))) {
            return $this->nextPayload(
                label: 'Follow up on estimate approval',
                reason: 'Customer decision pending',
                actionKey: 'open_conversations',
                section: 'conversations',
                decision: [
                    'question' => 'Why is NEXT suggesting follow up?',
                    'rule' => 'CustomerDecisionPending',
                    'factors' => [
                        ['label' => 'Repair order waiting approval', 'matched' => true],
                        ['label' => 'Customer decision pending', 'matched' => true],
                    ],
                ],
            );
        }

        if ($inspectionProgress['total'] > 0 && $inspectionProgress['remaining'] === 0) {
            return $this->nextPayload(
                label: 'Inspection complete',
                reason: 'All checklist items recorded',
                actionKey: 'inspection_complete',
                section: 'overview',
                decision: [
                    'question' => 'Why is NEXT suggesting inspection complete?',
                    'rule' => 'InspectionChecklistComplete',
                    'factors' => [
                        ['label' => 'All checklist items recorded', 'matched' => true],
                        ['label' => 'No unchecked items remain', 'matched' => true],
                    ],
                ],
            );
        }

        return null;
    }

    /**
     * @param  Collection<int, InspectionItem>  $orderedItems
     * @return array{item: ?InspectionItem, used_safety_priority: bool}
     */
    private function resolveNextInspectionItem(Collection $orderedItems, bool $customerWaiting): array
    {
        $unchecked = $orderedItems
            ->filter(fn (InspectionItem $item): bool => $item->observed_state === InspectionObservedState::NotChecked);

        if ($unchecked->isEmpty()) {
            return ['item' => null, 'used_safety_priority' => false];
        }

        if ($customerWaiting) {
            $safetyFirst = $unchecked->first(function (InspectionItem $item): bool {
                $intent = InspectionFindingIntent::tryFromNotes($item->notes);

                return $intent === InspectionFindingIntent::Safety;
            });

            if ($safetyFirst instanceof InspectionItem) {
                return ['item' => $safetyFirst, 'used_safety_priority' => true];
            }
        }

        return ['item' => $unchecked->first(), 'used_safety_priority' => false];
    }

    /**
     * @param  Collection<int, InspectionItem>  $orderedItems
     * @return array<string, mixed>
     */
    private function inspectItemDecision(
        InspectionItem $nextItem,
        Collection $orderedItems,
        bool $customerWaiting,
        bool $usedSafetyPriority,
    ): array {
        $factors = [];

        if ($customerWaiting) {
            $factors[] = ['label' => 'Customer waiting', 'matched' => true];
        }

        if ($usedSafetyPriority) {
            $factors[] = ['label' => 'Safety item prioritized', 'matched' => true];
        }

        $index = $orderedItems->search(fn (InspectionItem $candidate): bool => $candidate->id === $nextItem->id);
        if ($index !== false && $index > 0) {
            $priorItem = $orderedItems->get($index - 1);
            if ($priorItem instanceof InspectionItem
                && $priorItem->observed_state !== InspectionObservedState::NotChecked) {
                $factors[] = ['label' => $priorItem->label.' complete', 'matched' => true];
            }
        }

        $factors[] = ['label' => $nextItem->label.' incomplete', 'matched' => true];

        $rule = match (true) {
            $usedSafetyPriority => 'CustomerWaitingSafetyPriority',
            $customerWaiting => 'CustomerWaitingSequentialOrder',
            default => 'SequentialChecklistOrder',
        };

        return [
            'question' => 'Why is NEXT suggesting '.$nextItem->label.'?',
            'rule' => $rule,
            'factors' => $factors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function photoRequiredDecision(InspectionItem $item): array
    {
        return [
            'question' => 'Why is NEXT suggesting a photo for '.$item->label.'?',
            'rule' => 'EvidenceRequiredOnFlaggedItem',
            'factors' => [
                ['label' => 'Item flagged needs attention', 'matched' => true],
                ['label' => 'Photo evidence missing', 'matched' => true],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function confidence(): array
    {
        /** @var list<array{key: string, label: string, complete: bool}> */
        $catalog = [
            ['key' => 'workspace_projection', 'label' => 'Backend workspace projection', 'complete' => true],
            ['key' => 'health_header', 'label' => 'Health header', 'complete' => true],
            ['key' => 'next_engine', 'label' => 'Next engine', 'complete' => true],
            ['key' => 'decision_explainability', 'label' => 'Decision explainability', 'complete' => true],
            ['key' => 'command_bar', 'label' => 'Command bar', 'complete' => true],
            ['key' => 'role_sections', 'label' => 'Role-based sections', 'complete' => true],
            ['key' => 'inspection_flow', 'label' => 'Inspection flow', 'complete' => true],
            ['key' => 'living_inspection_record', 'label' => 'Living inspection record', 'complete' => true],
            ['key' => 'operational_timeline', 'label' => 'Operational timeline', 'complete' => true],
            ['key' => 'recommendation_queue', 'label' => 'Recommendation queue', 'complete' => true],
            ['key' => 'context_alerts', 'label' => 'Context alerts', 'complete' => true],
            ['key' => 'conversation_surface', 'label' => 'Conversation surface', 'complete' => true],
            ['key' => 'overview_intelligence', 'label' => 'Overview intelligence', 'complete' => true],
            ['key' => 'inspection_bootstrap', 'label' => 'Inspection bootstrap on open', 'complete' => true],
            ['key' => 'progressive_disclosure', 'label' => 'Progressive disclosure by role', 'complete' => true],
            ['key' => 'workspace_inspector', 'label' => 'Workspace Inspector', 'complete' => true],
            ['key' => 'prior_visit_item_history', 'label' => 'Prior visit history on items', 'complete' => true],
            ['key' => 'tablet_tri_pane', 'label' => 'Tablet tri-pane layout', 'complete' => true],
            ['key' => 'voice_input', 'label' => 'Voice input', 'complete' => false],
            ['key' => 'camera_persistence', 'label' => 'Camera persistence', 'complete' => false],
            ['key' => 'prior_visit_workspace', 'label' => 'Prior visit history on workspace', 'complete' => false],
        ];

        $complete = collect($catalog)->where('complete', true)->count();
        $total = count($catalog);

        return [
            'label' => 'Workspace Ready',
            'ready_fraction' => $total > 0 ? round($complete / $total, 3) : 0.0,
            'ready_percent' => $total > 0 ? (int) round(($complete / $total) * 100) : 0,
            'missing_intentional' => collect($catalog)
                ->where('complete', false)
                ->map(fn (array $item): array => [
                    'key' => $item['key'],
                    'label' => $item['label'],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $decision
     * @return array<string, mixed>
     */
    private function nextPayload(
        string $label,
        string $reason,
        string $actionKey,
        string $section,
        ?int $itemId = null,
        ?array $decision = null,
    ): array {
        return array_filter([
            'headline' => 'NEXT',
            'label' => $label,
            'reason' => $reason,
            'decision' => $decision,
            'action' => array_filter([
                'key' => $actionKey,
                'section' => $section,
                'item_id' => $itemId,
            ], fn ($value) => $value !== null),
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function alert(string $key, string $label, string $tone): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'tone' => $tone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function timelineEntry(
        CarbonInterface $occurredAt,
        string $headline,
        string $kind,
        ?string $body = null,
        ?string $actor = null,
        ?int $item_id = null,
    ): array {
        return [
            'occurred_at' => $occurredAt->toIso8601String(),
            'occurred_at_ts' => $occurredAt->getTimestamp(),
            'headline' => $headline,
            'body' => $body,
            'kind' => $kind,
            'actor' => $actor,
            'item_id' => $item_id,
        ];
    }

    private function intentLabel(InspectionItem $item): string
    {
        return (InspectionFindingIntent::tryFromNotes($item->notes) ?? InspectionFindingIntent::Safety)->label();
    }
}
