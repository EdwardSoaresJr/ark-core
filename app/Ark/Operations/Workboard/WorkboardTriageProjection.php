<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\Attention\AttentionPressureResolver;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Observations\OperationalObservation;
use App\Ark\Operations\Observations\OperationalObservationResolver;
use App\Ark\Operations\Observations\OperationalObservationSeverity;
use App\Ark\Operations\Observations\OperationalObservationType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Timeline\Mappers\CommunicationEventMapper;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class WorkboardTriageProjection
{
    /** @var array<int, list<OperationalObservation>> */
    private array $observationCache = [];

    private ?string $cardPassFingerprint = null;

    /** @var Collection<int, WorkboardTriageCard>|null */
    private ?Collection $memoizedCards = null;

    private ?string $memoizedCardsFingerprint = null;

    public function __construct(
        private readonly CommunicationEventMapper $communicationEventMapper,
        private readonly OperationalObservationResolver $observationResolver,
        private readonly AttentionPressureResolver $attentionPressureResolver,
    ) {}

    /**
     * Keep observation + card memo across home board / recommendations / attention
     * passes that share the same RO set on one request.
     *
     * @param  Collection<int, RepairOrder>  $repairOrders
     */
    private function beginCardPass(Collection $repairOrders): void
    {
        $fingerprint = implode(',', $repairOrders->modelKeys());

        if ($this->cardPassFingerprint === $fingerprint) {
            return;
        }

        $this->cardPassFingerprint = $fingerprint;
        $this->observationCache = [];
        $this->memoizedCards = null;
        $this->memoizedCardsFingerprint = null;
    }

    /**
     * @return list<WorkboardTriageCard>
     */
    public function cardsForRepairOrders(Collection $repairOrders): array
    {
        return $this->allTriageCardsForRepairOrders($repairOrders)->all();
    }

    /**
     * @return list<OperationalObservation>
     */
    public function observationsFor(RepairOrder $repairOrder): array
    {
        return $this->observationsForRepairOrder($repairOrder);
    }

    public function forAdvisorHomeBoard(Collection $repairOrders): array
    {
        $cardsById = $this->allTriageCardsForRepairOrders($repairOrders)
            ->keyBy(fn (WorkboardTriageCard $card): int => $card->repairOrder->id);

        /** @var array<string, Collection<int, WorkboardTriageCard>> $cardsByColumn */
        $cardsByColumn = collect(WorkboardSwimlaneCatalog::advisorHomeBoardColumns())
            ->mapWithKeys(fn (array $column): array => [$column['key'] => collect()])
            ->all();

        foreach ($repairOrders as $repairOrder) {
            $columnKey = WorkboardSwimlaneCatalog::homeBoardColumnKeyForRepairOrder($repairOrder);

            if ($columnKey === null) {
                continue;
            }

            $card = $cardsById->get($repairOrder->id);

            if ($card instanceof WorkboardTriageCard) {
                $cardsByColumn[$columnKey]->push($card);
            }
        }

        return collect(WorkboardSwimlaneCatalog::advisorHomeBoardColumns())
            ->map(function (array $column) use ($cardsByColumn): WorkboardTriageLaneProjection {
                $laneCards = ($cardsByColumn[$column['key']] ?? collect())
                    ->sortBy([
                        ['pressureScore', 'desc'],
                        ['ageMinutes', 'desc'],
                    ])
                    ->values();

                return new WorkboardTriageLaneProjection(
                    key: $column['key'],
                    label: $column['label'],
                    tone: $column['tone'],
                    totalCount: $laneCards->count(),
                    visibleCards: $laneCards->all(),
                    hiddenCount: 0,
                    viewAllUrl: null,
                    inventoryUrl: WorkboardSwimlaneCatalog::inventoryUrlForHomeColumn($column['key']),
                );
            })
            ->all();
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     */
    public function forAdvisor(Collection $repairOrders, bool $focusAttention = false): WorkboardTriageBoardProjection
    {
        $cardsByLane = $this->cardsByLane($repairOrders);
        $allCards = $cardsByLane->flatten(1);

        $attentionHeader = $this->attentionHeader($allCards);
        $pickupOverflow = $this->pickupOverflow($repairOrders, $cardsByLane->get('ready_pickup', collect()));

        $swimlanes = collect(WorkboardSwimlaneCatalog::advisorSwimlanes())
            ->map(function (array $swimlane) use ($cardsByLane): WorkboardTriageSwimlaneProjection {
                $lanes = collect($swimlane['lanes'])
                    ->map(function (array $lane) use ($cardsByLane): WorkboardTriageLaneProjection {
                        $laneCards = $cardsByLane->get($lane['key'], collect())->values();

                        if ($lane['key'] === 'ready_pickup') {
                            $laneCards = $laneCards
                                ->filter(fn (WorkboardTriageCard $card): bool => ! $card->countsAsOverduePickup)
                                ->values();
                        }

                        $visibleCards = $laneCards
                            ->take(WorkboardSwimlaneCatalog::VISIBLE_CARD_LIMIT)
                            ->all();
                        $hiddenCount = max(0, $laneCards->count() - count($visibleCards));
                        $inventoryUrl = $laneCards->isNotEmpty()
                            ? WorkboardSwimlaneCatalog::inventoryUrlForLane($lane['key'])
                            : null;

                        return new WorkboardTriageLaneProjection(
                            key: $lane['key'],
                            label: $lane['label'],
                            tone: $lane['tone'],
                            totalCount: $laneCards->count(),
                            visibleCards: $visibleCards,
                            hiddenCount: $hiddenCount,
                            viewAllUrl: $inventoryUrl !== null && $hiddenCount > 0
                                ? $inventoryUrl
                                : null,
                            inventoryUrl: $inventoryUrl,
                        );
                    })
                    ->all();

                return new WorkboardTriageSwimlaneProjection(
                    key: $swimlane['key'],
                    label: $swimlane['label'],
                    lanes: $lanes,
                );
            })
            ->all();

        return new WorkboardTriageBoardProjection(
            attentionHeader: $attentionHeader,
            swimlanes: $swimlanes,
            pickupOverflow: $pickupOverflow,
            queueCount: $allCards->count(),
            focusAttention: $focusAttention,
        );
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     */
    public function forAdvisorWorkspace(
        Collection $repairOrders,
        ?string $selectedQueueKey,
        bool $applyDefaultQueue = false,
    ): WorkboardQueueWorkspaceProjection {
        $this->beginCardPass($repairOrders);

        if ($repairOrders->isEmpty()) {
            return $this->emptyWorkspaceProjection($selectedQueueKey);
        }

        $navCounts = $this->navCountsFor($repairOrders);

        if ($applyDefaultQueue && $selectedQueueKey === null) {
            $selectedQueueKey = WorkboardQueueCatalog::defaultQueueFromCounts($navCounts);
        }

        $navGroups = $this->navGroupsFromCounts($navCounts, $selectedQueueKey);
        $pickupOverflow = $this->pickupOverflow($repairOrders, collect());

        $selectedQueueRepairOrders = $selectedQueueKey === null
            ? collect()
            : $this->repairOrdersMatchingQueue($repairOrders, $selectedQueueKey);

        $selectedQueueCards = $this->sortedCardsForRepairOrders($selectedQueueRepairOrders);
        $visibleCards = $selectedQueueCards
            ->take(WorkboardQueueCatalog::WORKSPACE_CARD_LIMIT)
            ->values()
            ->all();
        $hiddenCount = max(0, $selectedQueueCards->count() - count($visibleCards));
        $viewAllUrl = $this->inventoryUrlForQueue($selectedQueueKey, $hiddenCount);

        return new WorkboardQueueWorkspaceProjection(
            navGroups: $navGroups,
            selectedQueueKey: $selectedQueueKey,
            selectedQueueLabel: $selectedQueueKey !== null
                ? WorkboardQueueCatalog::labelForQueue($selectedQueueKey)
                : null,
            selectedQueueCount: $selectedQueueCards->count(),
            visibleCards: $visibleCards,
            hiddenCount: $hiddenCount,
            viewAllUrl: $viewAllUrl,
            pickupOverflow: $selectedQueueKey === 'ready_pickup' ? $pickupOverflow : null,
            queueCount: $navCounts['total'],
            boardIsEmpty: false,
            needsAttentionRollup: $navCounts['needs_attention'],
            needsAttentionRollupUrl: WorkboardQueueCatalog::needsAttentionRollupUrl($navCounts),
            needsAttentionRollupIsActive: $selectedQueueKey === WorkboardQueueCatalog::NEEDS_ATTENTION_QUEUE,
        );
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return array{
     *     lanes: array<string, int>,
     *     needs_attention: int,
     *     customer_waiting: int,
     *     unassigned: int,
     *     overdue_pickup: int,
     *     oldest_age_by_queue: array<string, int>,
     *     total: int
     * }
     */
    public function advisorNavCounts(Collection $repairOrders): array
    {
        $this->beginCardPass($repairOrders);

        return $this->navCountsFor($repairOrders);
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return Collection<int, RepairOrder>
     */
    public function repairOrdersInQueue(Collection $repairOrders, string $queueKey): Collection
    {
        $this->beginCardPass($repairOrders);

        return $this->repairOrdersMatchingQueue($repairOrders, $queueKey);
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return Collection<int, WorkboardTriageCard>
     */
    public function allTriageCardsForRepairOrders(Collection $repairOrders): Collection
    {
        $fingerprint = implode(',', $repairOrders->modelKeys());

        if ($this->memoizedCards !== null && $this->memoizedCardsFingerprint === $fingerprint) {
            return $this->memoizedCards;
        }

        $this->beginCardPass($repairOrders);

        $this->memoizedCards = $repairOrders
            ->map(fn (RepairOrder $repairOrder): ?WorkboardTriageCard => $this->cardForRepairOrder($repairOrder))
            ->filter()
            ->values();
        $this->memoizedCardsFingerprint = $fingerprint;

        return $this->memoizedCards;
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return Collection<int, WorkboardTriageCard>
     */
    public function triageCardsForRepairOrders(Collection $repairOrders): Collection
    {
        return $this->allTriageCardsForRepairOrders($repairOrders)
            ->filter(fn (WorkboardTriageCard $card): bool => $card->countsAsNeedsAttention)
            ->sortBy([
                ['pressureScore', 'desc'],
                ['ageMinutes', 'desc'],
            ])
            ->values();
    }

    private function emptyWorkspaceProjection(?string $selectedQueueKey): WorkboardQueueWorkspaceProjection
    {
        $emptyCounts = [
            'lanes' => [],
            'needs_attention' => 0,
            'customer_waiting' => 0,
            'unassigned' => 0,
            'overdue_pickup' => 0,
            'oldest_age_by_queue' => [],
            'total' => 0,
        ];

        return new WorkboardQueueWorkspaceProjection(
            navGroups: $this->navGroupsFromCounts($emptyCounts, $selectedQueueKey),
            selectedQueueKey: $selectedQueueKey,
            selectedQueueLabel: $selectedQueueKey !== null
                ? WorkboardQueueCatalog::labelForQueue($selectedQueueKey)
                : null,
            selectedQueueCount: 0,
            visibleCards: [],
            hiddenCount: 0,
            viewAllUrl: null,
            pickupOverflow: null,
            queueCount: 0,
            boardIsEmpty: true,
            needsAttentionRollup: 0,
            needsAttentionRollupUrl: null,
            needsAttentionRollupIsActive: $selectedQueueKey === WorkboardQueueCatalog::NEEDS_ATTENTION_QUEUE,
        );
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return array{
     *     lanes: array<string, int>,
     *     needs_attention: int,
     *     customer_waiting: int,
     *     unassigned: int,
     *     overdue_pickup: int,
     *     oldest_age_by_queue: array<string, int>,
     *     total: int
     * }
     */
    private function navCountsFor(Collection $repairOrders): array
    {
        $lanes = [];
        $needsAttention = 0;
        $customerWaiting = 0;
        $unassigned = 0;
        $overduePickup = 0;
        $oldestAgeByQueue = [];
        $total = 0;

        foreach ($repairOrders as $repairOrder) {
            $laneKey = WorkboardSwimlaneCatalog::laneKeyForRepairOrder($repairOrder);

            if ($laneKey === null) {
                continue;
            }

            $total++;
            $ageMinutes = (int) $repairOrder->updated_at->diffInMinutes();

            if ($this->isOverduePickup($repairOrder)) {
                $overduePickup++;
                $this->touchQueueAge($oldestAgeByQueue, 'overdue_pickup', $ageMinutes);
            }

            if ($laneKey === 'shop_floor' && $repairOrder->assigned_technician_id === null) {
                $unassigned++;
                $this->touchQueueAge($oldestAgeByQueue, 'unassigned', $ageMinutes);
            }

            if ($laneKey !== 'ready_pickup' || ! $this->isOverduePickup($repairOrder)) {
                $lanes[$laneKey] = ($lanes[$laneKey] ?? 0) + 1;
                $this->touchQueueAge($oldestAgeByQueue, $laneKey, $ageMinutes);
            }

            if ($this->repairOrderCountsAsCustomerWaiting($repairOrder)) {
                $customerWaiting++;
                $this->touchQueueAge($oldestAgeByQueue, 'customer_waiting', $ageMinutes);
            }

            if ($this->repairOrderCountsAsNeedsAttention($repairOrder, $laneKey)) {
                $needsAttention++;
            }
        }

        return [
            'lanes' => $lanes,
            'needs_attention' => $needsAttention,
            'customer_waiting' => $customerWaiting,
            'unassigned' => $unassigned,
            'overdue_pickup' => $overduePickup,
            'oldest_age_by_queue' => $oldestAgeByQueue,
            'total' => $total,
        ];
    }

    /**
     * @param  array<string, int>  $oldestAgeByQueue
     */
    private function touchQueueAge(array &$oldestAgeByQueue, string $queueKey, int $ageMinutes): void
    {
        $oldestAgeByQueue[$queueKey] = max($oldestAgeByQueue[$queueKey] ?? 0, $ageMinutes);
    }

    /**
     * @param  array{
     *     lanes: array<string, int>,
     *     needs_attention: int,
     *     customer_waiting: int,
     *     unassigned: int,
     *     overdue_pickup: int,
     *     oldest_age_by_queue: array<string, int>,
     *     total: int
     * }  $navCounts
     * @return list<WorkboardQueueNavGroup>
     */
    private function navGroupsFromCounts(array $navCounts, ?string $selectedQueueKey): array
    {
        $navGroups = [];

        foreach (WorkboardQueueCatalog::navSectionLabels() as $sectionKey => $sectionLabel) {
            $items = collect(WorkboardQueueCatalog::navQueueDefinitions())
                ->filter(fn (array $queue): bool => $queue['section'] === $sectionKey)
                ->map(function (array $queue) use ($navCounts, $selectedQueueKey): WorkboardQueueNavItem {
                    $count = WorkboardQueueCatalog::queueCountFromNavCounts($navCounts, $queue['key']);
                    $oldestAge = $navCounts['oldest_age_by_queue'][$queue['key']] ?? 0;

                    return new WorkboardQueueNavItem(
                        key: $queue['key'],
                        label: $queue['label'],
                        count: $count,
                        url: WorkboardQueueCatalog::queueUrl($queue['key']),
                        isActive: $selectedQueueKey === $queue['key'],
                        countSeverity: WorkboardQueueCatalog::countSeverityForQueue($queue['key'], $count, $oldestAge),
                    );
                })
                ->all();

            $navGroups[] = new WorkboardQueueNavGroup(
                label: $sectionLabel,
                items: $items,
            );
        }

        return $navGroups;
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return Collection<int, RepairOrder>
     */
    private function repairOrdersMatchingQueue(Collection $repairOrders, string $queueKey): Collection
    {
        if (WorkboardQueueCatalog::isNeedsAttentionQueue($queueKey)) {
            return $repairOrders
                ->filter(function (RepairOrder $repairOrder): bool {
                    $laneKey = WorkboardSwimlaneCatalog::laneKeyForRepairOrder($repairOrder);

                    if ($laneKey === null) {
                        return false;
                    }

                    return $this->repairOrderCountsAsNeedsAttention($repairOrder, $laneKey);
                })
                ->values();
        }

        if (WorkboardQueueCatalog::isCommunicationQueue($queueKey)) {
            return $repairOrders
                ->filter(function (RepairOrder $repairOrder) use ($queueKey): bool {
                    $laneKey = WorkboardSwimlaneCatalog::laneKeyForRepairOrder($repairOrder);

                    if ($laneKey === null) {
                        return false;
                    }

                    return match ($queueKey) {
                        'customer_waiting' => $this->repairOrderCountsAsCustomerWaiting($repairOrder),
                        'unassigned' => $laneKey === 'shop_floor' && $repairOrder->assigned_technician_id === null,
                        'overdue_pickup' => $this->isOverduePickup($repairOrder),
                        default => false,
                    };
                })
                ->values();
        }

        return $repairOrders
            ->filter(function (RepairOrder $repairOrder) use ($queueKey): bool {
                $laneKey = WorkboardSwimlaneCatalog::laneKeyForRepairOrder($repairOrder);

                if ($laneKey !== $queueKey) {
                    return false;
                }

                if ($queueKey === 'ready_pickup') {
                    return ! $this->isOverduePickup($repairOrder);
                }

                return true;
            })
            ->values();
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return Collection<int, WorkboardTriageCard>
     */
    private function sortedCardsForRepairOrders(Collection $repairOrders): Collection
    {
        return $repairOrders
            ->map(fn (RepairOrder $repairOrder): ?WorkboardTriageCard => $this->cardForRepairOrder($repairOrder))
            ->filter()
            ->sortBy([
                ['pressureScore', 'desc'],
                ['ageMinutes', 'desc'],
            ])
            ->values();
    }

    private function repairOrderCountsAsCustomerWaiting(RepairOrder $repairOrder): bool
    {
        if ($repairOrder->communicationEvents->isEmpty()) {
            return false;
        }

        return $this->countsAsCustomerWaiting($this->observationsForRepairOrder($repairOrder));
    }

    private function repairOrderCountsAsNeedsAttention(RepairOrder $repairOrder, string $laneKey): bool
    {
        if ($this->isOverduePickup($repairOrder)) {
            return true;
        }

        if ($laneKey === 'shop_floor' && $repairOrder->assigned_technician_id === null) {
            return true;
        }

        if ($this->shouldSurfacePartsPressure($laneKey) && $repairOrder->partsPressure()->showsChip()) {
            return true;
        }

        if ($repairOrder->vehicleIdentityPressure()->showsChip()) {
            return true;
        }

        if ($repairOrder->communicationEvents->isEmpty()) {
            return false;
        }

        $observations = $this->observationsForRepairOrder($repairOrder);
        $primaryObservation = $this->primaryObservation($observations);
        $signal = $this->signalFor($repairOrder, $laneKey, $primaryObservation);
        $pressureScore = $this->pressureScore($repairOrder, $observations, $signal);

        return $pressureScore >= 20
            || ($signal['tone'] ?? '') === 'alert'
            || ($signal['tone'] ?? '') === 'warn';
    }

    private function inventoryUrlForQueue(?string $queueKey, int $hiddenCount): ?string
    {
        if ($queueKey === null || $hiddenCount <= 0) {
            return null;
        }

        if (WorkboardQueueCatalog::isNeedsAttentionQueue($queueKey)) {
            return WorkboardQueueCatalog::inventoryUrlForNeedsAttentionQueue();
        }

        if (WorkboardQueueCatalog::isCommunicationQueue($queueKey)) {
            return WorkboardQueueCatalog::inventoryUrlForCommunicationQueue($queueKey);
        }

        return WorkboardSwimlaneCatalog::inventoryUrlForLane($queueKey);
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return Collection<string, Collection<int, WorkboardTriageCard>>
     */
    private function cardsByLane(Collection $repairOrders): Collection
    {
        return $repairOrders
            ->map(fn (RepairOrder $repairOrder): ?WorkboardTriageCard => $this->cardForRepairOrder($repairOrder))
            ->filter()
            ->groupBy(function (WorkboardTriageCard $card): string {
                $laneKey = WorkboardSwimlaneCatalog::laneKeyForRepairOrder($card->repairOrder);

                return $laneKey ?? 'unknown';
            })
            ->map(fn (Collection $cards): Collection => $cards
                ->sortBy([
                    ['pressureScore', 'desc'],
                    ['ageMinutes', 'desc'],
                ])
                ->values())
            ->filter(fn (Collection $cards, string $laneKey): bool => $laneKey !== 'unknown');
    }

    private function cardForRepairOrder(RepairOrder $repairOrder): ?WorkboardTriageCard
    {
        $laneKey = WorkboardSwimlaneCatalog::laneKeyForRepairOrder($repairOrder);

        if ($laneKey === null) {
            return null;
        }

        $observations = $this->observationsForRepairOrder($repairOrder);
        $primaryObservation = $this->primaryObservation($observations);
        $signal = $this->signalFor($repairOrder, $laneKey, $primaryObservation);
        $pressureScore = $this->pressureScore($repairOrder, $observations, $signal);
        $ageMinutes = (int) $repairOrder->updated_at->diffInMinutes();
        $ageLabel = str_replace(' ago', '', $repairOrder->updated_at->diffForHumans(short: true, parts: 1));
        $isOverduePickup = $this->isOverduePickup($repairOrder);
        $isUnassignedTech = $laneKey === 'shop_floor' && $repairOrder->assigned_technician_id === null;
        $countsAsCustomerWaiting = $this->countsAsCustomerWaiting($observations);
        $countsAsNeedsAttention = $pressureScore >= 20
            || ($signal['tone'] ?? '') === 'alert'
            || ($signal['tone'] ?? '') === 'warn';

        $concernSummary = trim((string) $repairOrder->concern_summary) ?: 'No concern recorded';

        return new WorkboardTriageCard(
            repairOrder: $repairOrder,
            vehicleLabel: $repairOrder->vehicle?->display_name ?? 'Unknown vehicle',
            concernSummary: $concernSummary,
            concernHeadline: $this->concernHeadline($concernSummary),
            signalLabel: $signal['label'],
            signalTone: $signal['tone'],
            ageLabel: $ageLabel,
            ageMinutes: $ageMinutes,
            pressureScore: $pressureScore,
            countsAsNeedsAttention: $countsAsNeedsAttention,
            countsAsCustomerWaiting: $countsAsCustomerWaiting,
            countsAsUnassigned: $isUnassignedTech,
            countsAsOverduePickup: $isOverduePickup,
            href: route('operations.repair-orders.show', $repairOrder),
        );
    }

    /**
     * @return list<OperationalObservation>
     */
    private function observationsForRepairOrder(RepairOrder $repairOrder): array
    {
        if (array_key_exists($repairOrder->id, $this->observationCache)) {
            return $this->observationCache[$repairOrder->id];
        }

        $events = $repairOrder->communicationEvents
            ->map(fn (CommunicationEvent $event): OperationalEventEntry => $this->communicationEventMapper->map($event))
            ->sortBy(fn (OperationalEventEntry $event): int => $event->occurredAt->timestamp)
            ->values()
            ->all();

        if ($events === []) {
            return $this->observationCache[$repairOrder->id] = [];
        }

        return $this->observationCache[$repairOrder->id] = $this->observationResolver->resolve($events, [
            'repair_order_id' => $repairOrder->id,
            'customer_id' => $repairOrder->customer_id,
            'vehicle_id' => $repairOrder->vehicle_id,
        ]);
    }

    /**
     * @param  list<OperationalObservation>  $observations
     */
    private function primaryObservation(array $observations): ?OperationalObservation
    {
        if ($observations === []) {
            return null;
        }

        return collect($observations)
            ->sortByDesc(fn (OperationalObservation $observation): int => $this->observationRank($observation))
            ->first();
    }

    private function observationRank(OperationalObservation $observation): int
    {
        $severityRank = match ($observation->severity) {
            OperationalObservationSeverity::High => 300,
            OperationalObservationSeverity::Medium => 200,
            OperationalObservationSeverity::Low => 100,
        };

        $typeRank = match ($observation->type) {
            OperationalObservationType::CustomerWaitingResponse,
            OperationalObservationType::CustomerSentMultipleMessages => 90,
            OperationalObservationType::EstimateViewedMultipleTimes => 80,
            OperationalObservationType::EstimateViewed => 70,
            OperationalObservationType::ConversationUnassigned => 60,
            OperationalObservationType::EstimateSent => 20,
            default => 10,
        };

        return $severityRank + $typeRank;
    }

    /**
     * @param  list<OperationalObservation>  $observations
     * @return array{label: ?string, tone: string}
     */
    private function signalFor(RepairOrder $repairOrder, string $laneKey, ?OperationalObservation $primaryObservation): array
    {
        if ($primaryObservation !== null && $this->shouldSurfaceObservation($primaryObservation)) {
            return [
                'label' => $primaryObservation->type->workboardSignalLabel(),
                'tone' => match ($primaryObservation->severity) {
                    OperationalObservationSeverity::High => 'alert',
                    OperationalObservationSeverity::Medium => 'warn',
                    OperationalObservationSeverity::Low => 'neutral',
                },
            ];
        }

        $partsPressure = $repairOrder->partsPressure();

        if ($this->shouldSurfacePartsPressure($laneKey) && $partsPressure->showsChip()) {
            return [
                'label' => $partsPressure->label(),
                'tone' => 'warn',
            ];
        }

        if ($laneKey === 'shop_floor' && $repairOrder->assigned_technician_id === null) {
            return [
                'label' => 'Unassigned Tech',
                'tone' => 'warn',
            ];
        }

        if ($repairOrder->vehicleIdentityPressure()->showsChip()) {
            return [
                'label' => 'Vehicle ID Needed',
                'tone' => 'warn',
            ];
        }

        if ($this->isOverduePickup($repairOrder)) {
            return [
                'label' => 'Overdue Pickup',
                'tone' => 'alert',
            ];
        }

        return [
            'label' => null,
            'tone' => 'neutral',
        ];
    }

    private function shouldSurfaceObservation(OperationalObservation $observation): bool
    {
        if ($observation->type === OperationalObservationType::EstimateSent) {
            return $observation->severity !== OperationalObservationSeverity::Low;
        }

        return true;
    }

    private function shouldSurfacePartsPressure(string $laneKey): bool
    {
        return in_array($laneKey, ['waiting_parts', 'shop_floor', 'quality_check', 'waiting_approval'], true);
    }

    /**
     * @param  list<OperationalObservation>  $observations
     * @param  array{label: ?string, tone: string}  $signal
     */
    private function pressureScore(RepairOrder $repairOrder, array $observations, array $signal): int
    {
        $candidate = $this->attentionPressureResolver->candidate(
            entityKey: 'repair_order:'.$repairOrder->id,
            headline: $repairOrder->vehicle?->display_name ?? 'Repair order',
            observations: $observations,
            repairOrderId: $repairOrder->id,
            customerId: $repairOrder->customer_id,
        );

        $score = $candidate?->pressureScore ?? 0;

        if ($signal['tone'] === 'alert') {
            $score = max($score, 40);
        } elseif ($signal['tone'] === 'warn') {
            $score = max($score, 24);
        }

        if ($this->isOverduePickup($repairOrder)) {
            $score = max($score, 36);
        }

        return $score;
    }

    /**
     * @param  list<OperationalObservation>  $observations
     */
    private function countsAsCustomerWaiting(array $observations): bool
    {
        foreach ($observations as $observation) {
            if (in_array($observation->type, [
                OperationalObservationType::CustomerWaitingResponse,
                OperationalObservationType::CustomerSentMultipleMessages,
                OperationalObservationType::EstimateViewed,
                OperationalObservationType::EstimateViewedMultipleTimes,
            ], true)) {
                return true;
            }
        }

        return false;
    }

    private function isOverduePickup(RepairOrder $repairOrder): bool
    {
        if (! WorkboardSwimlaneCatalog::isOutgoingPickupSlug($repairOrder->workboardLaneStatus()->value)) {
            return false;
        }

        return $repairOrder->updated_at->lt(now()->subDays(WorkboardSwimlaneCatalog::PICKUP_RECENT_DAYS));
    }

    /**
     * @param  Collection<int, WorkboardTriageCard>  $allCards
     */
    private function attentionHeader(Collection $allCards): WorkboardTriageAttentionHeader
    {
        $needsAttentionCount = $allCards->filter(fn (WorkboardTriageCard $card): bool => $card->countsAsNeedsAttention)->count();
        $customerWaitingCount = $allCards->filter(fn (WorkboardTriageCard $card): bool => $card->countsAsCustomerWaiting)->count();
        $unassignedCount = $allCards->filter(fn (WorkboardTriageCard $card): bool => $card->countsAsUnassigned)->count();
        $overduePickupCount = $allCards->filter(fn (WorkboardTriageCard $card): bool => $card->countsAsOverduePickup)->count();
        $firstAttentionCard = $this->firstCardMatching($allCards, fn (WorkboardTriageCard $card): bool => $card->countsAsNeedsAttention);

        return new WorkboardTriageAttentionHeader(
            needsAttention: $needsAttentionCount,
            customerWaiting: $customerWaitingCount,
            unassigned: $unassignedCount,
            overduePickup: $overduePickupCount,
            needsAttentionUrl: $needsAttentionCount > 0
                ? ($needsAttentionCount > WorkboardSwimlaneCatalog::VISIBLE_CARD_LIMIT
                    ? WorkboardAttentionInventoryQuery::inventoryUrl('needs_attention')
                    : $this->cardAnchorUrl($firstAttentionCard, focusAttention: true))
                : null,
            customerWaitingUrl: $customerWaitingCount > 0
                ? ($customerWaitingCount > WorkboardSwimlaneCatalog::VISIBLE_CARD_LIMIT
                    ? WorkboardAttentionInventoryQuery::inventoryUrl('customer_waiting')
                    : route('operations.index').'#ops-home-col-estimates')
                : null,
            unassignedUrl: $unassignedCount > 0
                ? route('operations.repair-orders.index', ['unassigned' => '1'])
                : null,
            overduePickupUrl: $overduePickupCount > 0
                ? route('operations.repair-orders.index', [
                    'status' => RepairOrderStatus::ReadyPickup->value,
                    'pickup' => 'stale',
                ])
                : null,
        );
    }

    /**
     * @param  Collection<int, WorkboardTriageCard>  $allCards
     * @param  callable(WorkboardTriageCard): bool  $predicate
     */
    private function firstCardMatching(Collection $allCards, callable $predicate): ?WorkboardTriageCard
    {
        return $allCards
            ->filter($predicate)
            ->sortBy([
                ['pressureScore', 'desc'],
                ['ageMinutes', 'desc'],
            ])
            ->first();
    }

    private function cardAnchorUrl(?WorkboardTriageCard $card, bool $focusAttention = false): string
    {
        $params = $focusAttention ? ['queue' => WorkboardQueueCatalog::NEEDS_ATTENTION_QUEUE] : [];

        return route('operations.index', $params).'#ops-card-ro-'.$card->repairOrder->repair_order_id;
    }

    private function concernHeadline(string $concernSummary): string
    {
        $normalized = Str::of($concernSummary)->squish()->value();

        if ($normalized === '' || $normalized === 'No concern recorded') {
            return 'No concern recorded';
        }

        return (string) Str::of($normalized)
            ->before('.')
            ->before(',')
            ->before(';')
            ->trim()
            ->limit(WorkboardQueueCatalog::CONCERN_HEADLINE_LIMIT, '…');
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @param  Collection<int, WorkboardTriageCard>  $readyPickupCards
     */
    private function pickupOverflow(Collection $repairOrders, Collection $readyPickupCards): ?WorkboardPickupOverflowProjection
    {
        $outgoingOrders = $repairOrders->filter(
            fn (RepairOrder $repairOrder): bool => WorkboardSwimlaneCatalog::isOutgoingPickupSlug($repairOrder->workboardLaneStatus()->value),
        );

        if ($outgoingOrders->isEmpty()) {
            return null;
        }

        $staleCount = $outgoingOrders
            ->filter(fn (RepairOrder $repairOrder): bool => $repairOrder->updated_at->lt(now()->subDays(WorkboardSwimlaneCatalog::PICKUP_RECENT_DAYS)))
            ->count();
        $totalCount = $outgoingOrders->count();

        if ($totalCount <= WorkboardSwimlaneCatalog::VISIBLE_CARD_LIMIT && $staleCount === 0) {
            return null;
        }

        return new WorkboardPickupOverflowProjection(
            totalAwaitingPickup: $outgoingOrders->count(),
            staleCount: $staleCount,
            viewQueueUrl: route('operations.repair-orders.index', [
                'status' => RepairOrderStatus::ReadyPickup->value,
                'pickup' => 'all',
            ]),
        );
    }
}
