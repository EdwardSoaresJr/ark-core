<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Commitments\OperationalCommitment;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\BalanceDueResult;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\Inspections\InspectionCaptureLinks;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleSelectProjection;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Workboard\WorkboardTriageCard;
use App\Ark\Operations\Workboard\WorkboardTriageLaneProjection;
use Illuminate\Support\Collection;

/**
 * Tekmetric-style operational surface for advisor home cards — chips, promise, recognition.
 */
final class AdvisorHomeCardSurfaceProjection
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDueCalculator,
    ) {}

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @param  list<WorkboardTriageLaneProjection>  $homeBoardColumns
     * @return array<int, AdvisorHomeCardSurface>
     */
    public function mapForHomeBoard(Collection $repairOrders, array $homeBoardColumns): array
    {
        if ($repairOrders->isEmpty()) {
            return [];
        }

        $cardsByRepairOrderId = $this->cardsByRepairOrderId($homeBoardColumns);
        $columnKeyByRepairOrderId = $this->columnKeyByRepairOrderId($homeBoardColumns);
        $balances = $this->balanceDueCalculator->mapForRepairOrders($repairOrders);
        $commitments = $this->nextOpenCommitmentsByRepairOrderId($repairOrders->modelKeys());
        $appointments = $this->nextActiveAppointmentsForRepairOrders($repairOrders);
        $estimateEvents = $this->latestEstimateEventsByRepairOrderId($repairOrders->modelKeys());
        $surfaces = [];

        foreach ($repairOrders as $repairOrder) {
            $card = $cardsByRepairOrderId[$repairOrder->id] ?? null;

            if (! $card instanceof WorkboardTriageCard) {
                continue;
            }

            $balance = $balances[$repairOrder->id] ?? new BalanceDueResult(
                hasIssuedInvoice: false,
                invoiceTotalCents: 0,
                depositsAppliedCents: 0,
                paymentsAppliedCents: 0,
                refundsAppliedCents: 0,
                adjustmentsCents: 0,
                creditsAppliedCents: 0,
                writeOffsCents: 0,
                balanceDueCents: 0,
                unappliedDepositsCents: 0,
                invoiceStatus: InvoiceStatus::Issued,
            );

            $customerHubUrl = $repairOrder->customer_id !== null
                ? route('operations.customers.show', $repairOrder->customer_id)
                : null;
            $customerPhone = filled($repairOrder->customer?->display_phone)
                ? $repairOrder->customer->display_phone
                : null;

            $chip = $this->resolveChip($repairOrder, $card, $balance);

            $surfaces[$repairOrder->id] = new AdvisorHomeCardSurface(
                chip: $chip,
                customerPhone: $customerPhone,
                techInitials: $this->techInitials($repairOrder->repairActionOwnerSummary()),
                promiseLabel: $this->promiseLabel($commitments[$repairOrder->id] ?? null),
                promiseTone: $this->promiseTone($commitments[$repairOrder->id] ?? null),
                vehicleOnSite: (bool) ($repairOrder->waiting_here || $repairOrder->drop_off),
                laborProgress: $this->laborProgress(
                    $repairOrder,
                    $columnKeyByRepairOrderId[$repairOrder->id] ?? null,
                ),
                customerHubUrl: $customerHubUrl,
                textCustomerUrl: $customerHubUrl !== null && filled($customerPhone)
                    ? $customerHubUrl.'?compose=text#customer-communication'
                    : null,
                recordFindingUrl: InspectionCaptureLinks::canRecord(auth()->user(), $repairOrder)
                    ? InspectionCaptureLinks::captureUrl($repairOrder)
                    : null,
                estimateEventLabel: $estimateEvents[$repairOrder->id]['label'] ?? null,
                estimateEventKind: $estimateEvents[$repairOrder->id]['kind'] ?? null,
                statusMoves: $this->statusMoves($repairOrder),
                concernLabel: $this->concernLabel($card),
                nextMoveLabel: $card->nextMoveLabel($chip->label),
                scheduleLabel: $this->scheduleLabel($appointments[$repairOrder->id] ?? null),
                scheduleTone: $this->scheduleTone($appointments[$repairOrder->id] ?? null),
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
     * Same choices as the RO lifecycle select (status + close), including disabled rows.
     * Close choices that need confirmation deep-link to the RO workspace.
     *
     * @return list<array{
     *     value: string,
     *     label: string,
     *     disabled: bool,
     *     blockedReason: ?string,
     *     needsRoConfirmation: bool
     * }>
     */
    private function statusMoves(RepairOrder $repairOrder): array
    {
        if ($repairOrder->isTerminal()) {
            return [];
        }

        return RepairOrderLifecycleSelectProjection::forCatalogTargets(
            $repairOrder,
            auth()->user(),
        )->boardMoves();
    }

    private function concernLabel(WorkboardTriageCard $card): ?string
    {
        $headline = trim($card->concernHeadline);

        if ($headline === '' || strcasecmp($headline, 'No concern recorded') === 0) {
            return null;
        }

        return $headline;
    }

    private function laborProgress(RepairOrder $repairOrder, ?string $homeColumnKey): ?AdvisorHomeLaborProgress
    {
        if (! in_array($homeColumnKey, ['work_in_progress', 'parts'], true)) {
            return null;
        }

        $billedHours = $this->billedApprovedLaborHours($repairOrder);

        if ($billedHours <= 0) {
            return null;
        }

        $completedHours = $this->completedApprovedLaborHours($repairOrder);
        $percent = $billedHours > 0
            ? (int) round(($completedHours / $billedHours) * 100)
            : 0;

        if ($percent === 0) {
            return null;
        }

        return new AdvisorHomeLaborProgress(
            completedHours: $completedHours,
            billedHours: $billedHours,
            percent: min(100, max(0, $percent)),
            label: sprintf('%.1f of %.1f hrs complete', $completedHours, $billedHours),
        );
    }

    private function completedApprovedLaborHours(RepairOrder $repairOrder): float
    {
        $total = 0.0;

        foreach ($repairOrder->lines as $line) {
            if (! $line instanceof RepairOrderLine || ! $line->type->isLabor()) {
                continue;
            }

            $concern = $line->concern;

            if ($concern === null
                || $concern->disposition !== RepairOrderConcernDisposition::Approved
                || ! $concern->productionStatus()->countsLaborComplete()) {
                continue;
            }

            $hours = $line->labor_billed_hours ?? $line->quantity;
            $total += (float) $hours;
        }

        return round($total, 2);
    }

    private function billedApprovedLaborHours(RepairOrder $repairOrder): float
    {
        $total = 0.0;

        foreach ($repairOrder->lines as $line) {
            if (! $line instanceof RepairOrderLine || ! $line->type->isLabor()) {
                continue;
            }

            if ($line->concern?->disposition !== RepairOrderConcernDisposition::Approved) {
                continue;
            }

            $hours = $line->labor_billed_hours ?? $line->quantity;
            $total += (float) $hours;
        }

        return round($total, 2);
    }

    /**
     * Next active appointment per board RO.
     * Prefer the appointment linked to this RO. Floor bookings often sit on the vehicle
     * with repair_order_id null (or an old closed RO) — still project onto the open card.
     *
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return array<int, Appointment>
     */
    private function nextActiveAppointmentsForRepairOrders(Collection $repairOrders): array
    {
        if ($repairOrders->isEmpty()) {
            return [];
        }

        $repairOrderIds = $repairOrders->modelKeys();
        $vehicleIds = $repairOrders->pluck('vehicle_id')->filter()->unique()->values()->all();

        $linked = [];
        $byVehicle = [];

        $appointments = Appointment::query()
            ->whereIn('status', [
                AppointmentStatus::Scheduled,
                AppointmentStatus::Confirmed,
                AppointmentStatus::Arrived,
            ])
            ->where(function ($query) use ($repairOrderIds, $vehicleIds): void {
                $query->whereIn('repair_order_id', $repairOrderIds);

                if ($vehicleIds !== []) {
                    $query->orWhereIn('vehicle_id', $vehicleIds);
                }
            })
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [AppointmentStatus::Arrived->value])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        foreach ($appointments as $appointment) {
            if ($appointment->repair_order_id !== null) {
                $linked[(int) $appointment->repair_order_id] ??= $appointment;
            }

            if ($appointment->vehicle_id !== null) {
                $byVehicle[(int) $appointment->vehicle_id] ??= $appointment;
            }
        }

        $mapped = [];

        foreach ($repairOrders as $repairOrder) {
            $mapped[$repairOrder->id] = $linked[$repairOrder->id]
                ?? ($repairOrder->vehicle_id !== null ? ($byVehicle[(int) $repairOrder->vehicle_id] ?? null) : null);
        }

        return array_filter($mapped);
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
     * Latest estimate sent/viewed touch per RO — "Viewed an hour ago" card footers.
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
        WorkboardTriageCard $card, // kept for call-site/reflection compatibility
        BalanceDueResult $balance,
    ): AdvisorHomeCardChip {
        // Financial end-state still outranks lifecycle copy on Completed cards.
        if ($balance->hasIssuedInvoice && $balance->balanceDueCents > 0) {
            return new AdvisorHomeCardChip('Balance Due', 'alert');
        }

        if ($repairOrder->readyToPost()) {
            return new AdvisorHomeCardChip('Ready to Post', 'ready');
        }

        // Status pill is the lifecycle control — label must match the RO status
        // (and Move-to choices), not sticky pressure copy that survives column moves.
        return $this->lifecycleChip($repairOrder);
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
