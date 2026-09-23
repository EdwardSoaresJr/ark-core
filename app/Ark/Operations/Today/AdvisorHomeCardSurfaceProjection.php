<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Commitments\OperationalCommitment;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Inspections\InspectionCaptureLinks;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleSelectProjection;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\Workboard\WorkboardCardActivityProjection;
use App\Ark\Operations\Workboard\WorkboardCardGlance;
use App\Ark\Operations\Workboard\WorkboardCardGlanceProjection;
use App\Ark\Operations\Workboard\WorkboardTriageCard;
use App\Ark\Operations\Workboard\WorkboardTriageLaneProjection;
use Illuminate\Support\Collection;

/**
 * Tekmetric-style operational surface for advisor home cards - chips, promise, recognition.
 */
final class AdvisorHomeCardSurfaceProjection
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDueCalculator,
        private readonly WorkboardCardGlanceProjection $cardGlance,
        private readonly WorkboardCardActivityProjection $cardActivity,
    ) {}

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @param  list<WorkboardTriageLaneProjection>  $homeBoardColumns
     * @param  Collection<int, EstimateTotals>|null  $repairOrderTotals
     * @return array<int, AdvisorHomeCardSurface>
     */
    public function mapForHomeBoard(Collection $repairOrders, array $homeBoardColumns, ?Collection $repairOrderTotals = null): array
    {
        if ($repairOrders->isEmpty()) {
            return [];
        }

        $cardsByRepairOrderId = $this->cardsByRepairOrderId($homeBoardColumns);
        $columnKeyByRepairOrderId = $this->columnKeyByRepairOrderId($homeBoardColumns);
        $balances = $this->balanceDueCalculator->mapForRepairOrders($repairOrders);
        $commitments = $this->nextOpenCommitmentsByRepairOrderId($repairOrders->modelKeys());
        $appointments = $this->cardActivity->appointmentsFor($repairOrders);
        $estimateEvents = $this->latestEstimateEventsByRepairOrderId($repairOrders->modelKeys());
        $activities = $this->cardActivity->map($repairOrders, $appointments);
        $glances = $this->cardGlance->map(
            $repairOrders,
            $cardsByRepairOrderId,
            $repairOrderTotals ?? collect(),
            $balances,
            $columnKeyByRepairOrderId,
        );
        $surfaces = [];

        foreach ($repairOrders as $repairOrder) {
            $card = $cardsByRepairOrderId[$repairOrder->id] ?? null;

            if (! $card instanceof WorkboardTriageCard) {
                continue;
            }

            $customerHubUrl = $repairOrder->customer_id !== null
                ? route('operations.customers.show', $repairOrder->customer_id)
                : null;
            $customerPhone = filled($repairOrder->customer?->display_phone)
                ? $repairOrder->customer->display_phone
                : null;

            $glance = $glances[$repairOrder->id] ?? null;
            $chip = $this->resolveChip($repairOrder, $glance);
            $attention = $glance instanceof WorkboardCardGlance
                ? $this->attentionWithSurface($glance->attention, $commitments[$repairOrder->id] ?? null, $appointments[$repairOrder->id] ?? null)
                : 'normal';
            $exception = $this->exceptionSlot(
                $card,
                $glance,
                $commitments[$repairOrder->id] ?? null,
                $appointments[$repairOrder->id] ?? null,
                $columnKeyByRepairOrderId[$repairOrder->id] ?? '',
            );

            $surfaces[$repairOrder->id] = new AdvisorHomeCardSurface(
                chip: $chip,
                customerPhone: $customerPhone,
                techInitials: $this->techInitials($repairOrder->repairActionOwnerSummary()),
                promiseLabel: $this->promiseLabel($commitments[$repairOrder->id] ?? null),
                promiseTone: $this->promiseTone($commitments[$repairOrder->id] ?? null),
                vehicleOnSite: (bool) ($repairOrder->waiting_here || $repairOrder->drop_off),
                laborProgress: null,
                customerHubUrl: $customerHubUrl,
                textCustomerUrl: $customerHubUrl !== null && filled($customerPhone)
                    ? $customerHubUrl.'?compose=text#customer-communication'
                    : null,
                recordFindingUrl: InspectionCaptureLinks::canRecord(auth()->user(), $repairOrder)
                    ? InspectionCaptureLinks::captureUrl($repairOrder)
                    : null,
                estimateEventLabel: $estimateEvents[$repairOrder->id]['label'] ?? null,
                estimateEventKind: $estimateEvents[$repairOrder->id]['kind'] ?? null,
                statusMoves: [],
                concernLabel: $this->concernLabel($card),
                nextMoveLabel: $glance instanceof WorkboardCardGlance ? $glance->nextLabel : $card->nextMoveLabel($chip->label),
                scheduleLabel: $this->scheduleLabel($appointments[$repairOrder->id] ?? null),
                scheduleTone: $this->scheduleTone($appointments[$repairOrder->id] ?? null),
                whyLabel: $glance?->whyLabel,
                moneyLabel: $glance?->moneyLabel,
                moneyCaption: $glance?->moneyCaption,
                waitAgeLabel: $glance?->ageLabel,
                waitingOnCustomerDecision: $glance?->waitingOnCustomerDecision ?? false,
                operationalStatus: $glance?->operationalStatus,
                clockLabel: $glance?->clockLabel,
                attention: $attention,
                statusRestatesLane: false,
                configuredStatusColor: $glance?->configuredStatusColor,
                activityMarks: $activities[$repairOrder->id] ?? WorkboardCardActivityProjection::idle(),
                exceptionLabel: $exception['label'],
                exceptionExtraCount: $exception['extra'],
                exceptionTone: $exception['tone'],
                exceptionItems: $exception['items'],
            );
        }

        return $surfaces;
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return list<AdvisorHomeBoardTechnicianOption>
     */
    public function technicianOptions(Collection $repairOrders): array
    {
        return $repairOrders
            ->pluck('assignedTechnician')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn ($technician): AdvisorHomeBoardTechnicianOption => new AdvisorHomeBoardTechnicianOption(
                id: (int) $technician->id,
                name: (string) $technician->name,
                initials: $this->techInitials($technician->name) ?? '?',
            ))
            ->all();
    }

    /**
     * @param  list<WorkboardTriageLaneProjection>  $homeBoardColumns
     * @return array<int, WorkboardTriageCard>
     */
    private function cardsByRepairOrderId(array $homeBoardColumns): array
    {
        $cards = [];

        foreach ($homeBoardColumns as $column) {
            foreach ($column->visibleCards as $card) {
                if ($card instanceof WorkboardTriageCard) {
                    $cards[$card->repairOrder->id] = $card;
                }
            }
        }

        return $cards;
    }

    /**
     * @param  list<WorkboardTriageLaneProjection>  $homeBoardColumns
     * @return array<int, string>
     */
    private function columnKeyByRepairOrderId(array $homeBoardColumns): array
    {
        $columns = [];

        foreach ($homeBoardColumns as $column) {
            foreach ($column->visibleCards as $card) {
                if ($card instanceof WorkboardTriageCard) {
                    $columns[$card->repairOrder->id] = $column->key;
                }
            }
        }

        return $columns;
    }

    /**
     * @return array{label: ?string, extra: int, tone: string}
     */
    private function exceptionSlot(
        WorkboardTriageCard $card,
        ?WorkboardCardGlance $glance,
        ?OperationalCommitment $commitment,
        ?Appointment $appointment,
        string $columnKey,
    ): array {
        $candidates = [];

        if ($card->countsAsOverduePickup) {
            $candidates[] = ['Pickup overdue', 'critical', 40];
        }

        if ($this->scheduleTone($appointment) === 'missed') {
            $candidates[] = ['Missed appointment', 'critical', 35];
        }

        if ($this->promiseTone($commitment) === 'overdue') {
            $candidates[] = ['Promise overdue', 'critical', 30];
        }

        if ($glance?->whyLabel === 'Deferred work due') {
            $candidates[] = ['Follow-up overdue', 'attention', 25];
        }

        $ageHours = $this->ageHours($glance?->ageLabel ?? '');

        if (($glance?->waitingOnCustomerDecision ?? false) && $ageHours >= 48) {
            $candidates[] = ['Follow-up overdue', 'attention', 20];
        }

        if ($columnKey === 'parts' && $ageHours >= 48) {
            $candidates[] = ['Parts overdue', 'attention', 15];
        }

        if ($card->repairOrder->partsPressure()->showsChip()) {
            $candidates[] = ['Needs parts', 'attention', 16];
        }

        usort($candidates, fn (array $left, array $right): int => $right[2] <=> $left[2]);

        if ($candidates === []) {
            return ['label' => null, 'extra' => 0, 'tone' => 'none', 'items' => []];
        }

        $uniqueLabels = [];

        foreach ($candidates as $candidate) {
            $uniqueLabels[$candidate[0]] = $candidate;
        }

        $ordered = array_values($uniqueLabels);

        return [
            'label' => $ordered[0][0],
            'extra' => max(0, count($ordered) - 1),
            'tone' => $ordered[0][1],
            'items' => array_map(
                fn (array $candidate): array => ['label' => $candidate[0], 'tone' => $candidate[1]],
                $ordered,
            ),
        ];
    }

    private function ageHours(string $ageLabel): int
    {
        if (preg_match('/(\d+)\s*w/i', $ageLabel, $match) === 1) {
            return (int) $match[1] * 168;
        }

        if (preg_match('/(\d+)\s*d/i', $ageLabel, $match) === 1) {
            return (int) $match[1] * 24;
        }

        if (preg_match('/(\d+)\s*h/i', $ageLabel, $match) === 1) {
            return (int) $match[1];
        }

        return 0;
    }

    private function concernLabel(WorkboardTriageCard $card): ?string
    {
        $headline = trim($card->concernHeadline);

        if ($headline === '' || strcasecmp($headline, 'No concern recorded') === 0) {
            return null;
        }

        return $headline;
    }

    private function scheduleLabel(?Appointment $appointment): ?string
    {
        if ($appointment === null) {
            return null;
        }

        if ($appointment->status === AppointmentStatus::Arrived) {
            return 'Checked in';
        }

        $startsAt = ShopDisplayTimezone::present($appointment->starts_at);
        $now = ShopDisplayTimezone::now();

        if ($startsAt->isSameDay($now)) {
            return 'Appointment · Today '.$startsAt->format('g:i A');
        }

        if ($startsAt->isSameDay($now->copy()->addDay())) {
            return 'Appointment · Tomorrow '.$startsAt->format('g:i A');
        }

        $when = $startsAt->format('D M j, g:i A');

        return $startsAt->lt($now) ? 'Missed appointment · '.$when : 'Appointment · '.$when;
    }

    private function scheduleTone(?Appointment $appointment): string
    {
        if ($appointment === null) {
            return 'none';
        }

        if ($appointment->status === AppointmentStatus::Arrived) {
            return 'arrived';
        }

        $startsAt = ShopDisplayTimezone::present($appointment->starts_at);

        if ($startsAt->lt(ShopDisplayTimezone::now())) {
            return 'missed';
        }

        if ($startsAt->isSameDay(ShopDisplayTimezone::now())) {
            return 'today';
        }

        return 'upcoming';
    }

    /**
     * @param  list<int|string>  $repairOrderIds
     * @return array<int, OperationalCommitment>
     */
    private function nextOpenCommitmentsByRepairOrderId(array $repairOrderIds): array
    {
        if ($repairOrderIds === []) {
            return [];
        }

        $commitments = [];

        foreach (
            OperationalCommitment::query()
                ->open()
                ->whereIn('repair_order_id', $repairOrderIds)
                ->orderBy('due_at')
                ->orderBy('id')
                ->get() as $commitment
        ) {
            $commitments[$commitment->repair_order_id] ??= $commitment;
        }

        return $commitments;
    }

    /**
     * Latest estimate sent/viewed touch per RO - "Viewed an hour ago" card footers.
     *
     * @param  list<int|string>  $repairOrderIds
     * @return array<int, array{label: string, kind: string}>
     */
    private function latestEstimateEventsByRepairOrderId(array $repairOrderIds): array
    {
        if ($repairOrderIds === []) {
            return [];
        }

        $events = [];

        foreach (
            CommunicationEvent::query()
                ->whereIn('repair_order_id', $repairOrderIds)
                ->whereIn('event_type', [
                    OperationalCommunicationType::EstimateSent->value,
                    OperationalCommunicationType::EstimateViewed->value,
                ])
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->get(['repair_order_id', 'event_type', 'occurred_at']) as $event
        ) {
            if (isset($events[$event->repair_order_id])) {
                continue;
            }

            $isViewed = $event->event_type === OperationalCommunicationType::EstimateViewed;

            $events[$event->repair_order_id] = [
                'label' => ($isViewed ? 'Viewed ' : 'Sent ').$event->occurred_at->diffForHumans(short: true, parts: 1),
                'kind' => $isViewed ? 'viewed' : 'sent',
            ];
        }

        return $events;
    }

    private function resolveChip(
        RepairOrder $repairOrder,
        ?WorkboardCardGlance $glance = null,
    ): AdvisorHomeCardChip {
        if ($glance instanceof WorkboardCardGlance) {
            return new AdvisorHomeCardChip(
                $glance->operationalStatus,
                'quiet',
                $glance->configuredStatusColor,
            );
        }

        return $this->lifecycleChip($repairOrder);
    }

    /**
     * @param  WorkboardCardGlance::ATTENTION_*  $attention
     */
    private function attentionWithSurface(string $attention, ?OperationalCommitment $commitment, ?Appointment $appointment): string
    {
        $rank = [
            WorkboardCardGlance::ATTENTION_NORMAL => 0,
            WorkboardCardGlance::ATTENTION_WATCH => 1,
            WorkboardCardGlance::ATTENTION_ATTENTION => 2,
            WorkboardCardGlance::ATTENTION_CRITICAL => 3,
        ];

        $current = $rank[$attention] ?? 0;

        if ($this->promiseTone($commitment) === 'overdue') {
            $current = max($current, $rank[WorkboardCardGlance::ATTENTION_CRITICAL]);
        }

        if ($appointment !== null && $this->scheduleTone($appointment) === 'missed') {
            $current = max($current, $rank[WorkboardCardGlance::ATTENTION_WATCH]);
        }

        return array_flip($rank)[$current] ?? WorkboardCardGlance::ATTENTION_NORMAL;
    }

    private function lifecycleChip(RepairOrder $repairOrder): AdvisorHomeCardChip
    {
        return new AdvisorHomeCardChip(
            $repairOrder->statusDisplayLabel(),
            RepairOrderLifecycleSelectProjection::statusTone($repairOrder),
        );
    }

    private function techInitials(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $name) ?: [];

        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1).substr($parts[1], 0, 1));
        }

        return strtoupper(substr($name, 0, 2));
    }

    private function promiseLabel(?OperationalCommitment $commitment): ?string
    {
        if (! $commitment instanceof OperationalCommitment) {
            return null;
        }

        $shopNow = ShopDisplayTimezone::now();
        $dueAtShop = $commitment->due_at->copy()->timezone(ShopDisplayTimezone::resolve());
        $startOfToday = $shopNow->copy()->startOfDay();
        $endOfToday = $shopNow->copy()->endOfDay();

        if ($dueAtShop->lt($startOfToday)) {
            return 'Promise overdue · '.$dueAtShop->format('M j, g:i A');
        }

        if ($dueAtShop->lte($endOfToday)) {
            return 'Due '.$dueAtShop->format('g:i A');
        }

        return 'Promise '.$dueAtShop->format('D g:i A');
    }

    private function promiseTone(?OperationalCommitment $commitment): string
    {
        if (! $commitment instanceof OperationalCommitment) {
            return 'none';
        }

        $shopNow = ShopDisplayTimezone::now();
        $dueAtShop = $commitment->due_at->copy()->timezone(ShopDisplayTimezone::resolve());

        if ($dueAtShop->lt($shopNow->copy()->startOfDay())) {
            return 'overdue';
        }

        if ($dueAtShop->lte($shopNow->copy()->endOfDay())) {
            return 'today';
        }

        return 'future';
    }
}
