<?php

namespace App\Ark\Operations\Attention;

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Work\CustomerDecisionSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Read-only Attention projection — dollars waiting on customer decisions.
 *
 * Authority remains on RepairOrder, lines, approvals, payments, and communications.
 *
 * Paid exclusion uses RepairOrder::isPaid() (ledger / BalanceDueCalculator only).
 * Legacy payment_status mirrors must not create false calm on Attention.
 */
final class CustomerDecisionPressure
{
    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
    ) {}

    /**
     * @return array{
     *     estimate_ready_not_sent: list<array<string, mixed>>,
     *     customer_decision_needed: list<array<string, mixed>>,
     *     approved_work_stalled: list<array<string, mixed>>,
     *     scheduled_later: list<array<string, mixed>>,
     *     total_count: int,
     *     total_dollars_at_risk_cents: int,
     * }
     */
    public function resolve(?User $viewer = null): array
    {
        $viewerUserId = $viewer?->id;
        $repairOrders = RepairOrder::query()
            ->with([
                'customer:id,first_name,last_name,phone',
                'vehicle:id,year,make,model',
                'lines.concern',
                'communicationEvents:id,repair_order_id,event_type,direction,occurred_at',
            ])
            ->where('status', '!=', RepairOrderStatus::Closed->value)
            ->orderByDesc('updated_at')
            ->get();

        $scheduleMap = $this->activeScheduleMap($repairOrders);

        $estimateReadyNotSent = [];
        $customerDecisionNeeded = [];
        $approvedWorkStalled = [];
        $scheduledLater = [];

        foreach ($repairOrders as $repairOrder) {
            if ($this->isPaid($repairOrder)) {
                continue;
            }

            $schedule = $this->effectiveSchedule($repairOrder, $scheduleMap);
            $estimateTotalCents = $this->totalsCalculator->totalsFor($repairOrder)->totalCents();
            $approvedTotalCents = $this->totalsCalculator->approvedTotalsForRead($repairOrder)->totalCents();

            if ($approvedTotalCents > 0 && $this->isApprovedWorkStalled($repairOrder)) {
                $row = $this->presentRow(
                    kind: 'approved_work_stalled',
                    repairOrder: $repairOrder,
                    dollarsAtRiskCents: $approvedTotalCents,
                    referenceAt: $this->approvedWorkReferenceAt($repairOrder),
                    detail: $this->approvedWorkDetail($repairOrder),
                    schedule: $schedule,
                );
                $this->bucketRow($row, $schedule, $approvedWorkStalled, $scheduledLater);

                continue;
            }

            if ($estimateTotalCents <= 0 || $approvedTotalCents > 0) {
                continue;
            }

            $hasEstimateSent = $this->hasEstimateSent($repairOrder);
            $awaitingApproval = $this->isAwaitingCustomerDecision($repairOrder);

            if (! $hasEstimateSent && ! $awaitingApproval) {
                $row = $this->presentRow(
                    kind: 'estimate_ready_not_sent',
                    repairOrder: $repairOrder,
                    dollarsAtRiskCents: $estimateTotalCents,
                    referenceAt: $this->estimateReadyAt($repairOrder),
                    detail: 'No customer send recorded',
                    schedule: $schedule,
                );
                $this->bucketRow($row, $schedule, $estimateReadyNotSent, $scheduledLater);

                continue;
            }

            $row = $this->presentRow(
                kind: 'customer_decision_needed',
                repairOrder: $repairOrder,
                dollarsAtRiskCents: $estimateTotalCents,
                referenceAt: $this->customerDecisionReferenceAt($repairOrder, $hasEstimateSent),
                detail: $this->customerDecisionDetail($repairOrder, $hasEstimateSent),
                lastCustomerActivity: $this->lastCustomerActivity($repairOrder, $hasEstimateSent),
                ageContext: $hasEstimateSent ? 'since_estimate_sent' : null,
                schedule: $schedule,
            );
            $this->bucketRow($row, $schedule, $customerDecisionNeeded, $scheduledLater);
        }

        $estimateReadyNotSent = $this->sortRowsForViewer($estimateReadyNotSent, $viewerUserId);
        $customerDecisionNeeded = $this->sortRowsForViewer($customerDecisionNeeded, $viewerUserId);
        $approvedWorkStalled = $this->sortRowsForViewer($approvedWorkStalled, $viewerUserId);
        $scheduledLater = $this->sortScheduledLaterForViewer($scheduledLater, $viewerUserId);

        $allRows = array_merge($estimateReadyNotSent, $customerDecisionNeeded, $approvedWorkStalled);

        return [
            'estimate_ready_not_sent' => $estimateReadyNotSent,
            'customer_decision_needed' => $customerDecisionNeeded,
            'approved_work_stalled' => $approvedWorkStalled,
            'scheduled_later' => $scheduledLater,
            'total_count' => count($allRows),
            'total_dollars_at_risk_cents' => array_sum(array_column($allRows, 'dollars_at_risk_cents')),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $visible
     * @param  list<array<string, mixed>>  $scheduledLater
     */
    private function bucketRow(
        array $row,
        ?CustomerDecisionSchedule $schedule,
        array &$visible,
        array &$scheduledLater,
    ): void {
        if ($schedule instanceof CustomerDecisionSchedule && $schedule->isSnoozed()) {
            $scheduledLater[] = $row;

            return;
        }

        $visible[] = $row;
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return array{
     *     ro: Collection<int|string, CustomerDecisionSchedule>,
     *     customer: Collection<int|string, CustomerDecisionSchedule>,
     * }
     */
    private function activeScheduleMap(Collection $repairOrders): array
    {
        $repairOrderIds = $repairOrders->pluck('id')->filter()->values();
        $customerIds = $repairOrders->pluck('customer_id')->filter()->unique()->values();

        return [
            'ro' => CustomerDecisionSchedule::query()
                ->whereNull('cleared_at')
                ->whereIn('repair_order_id', $repairOrderIds)
                ->with(['creator:id,name'])
                ->get()
                ->keyBy('repair_order_id'),
            'customer' => CustomerDecisionSchedule::query()
                ->whereNull('cleared_at')
                ->whereNull('repair_order_id')
                ->whereIn('customer_id', $customerIds)
                ->with(['creator:id,name'])
                ->get()
                ->keyBy('customer_id'),
        ];
    }

    /**
     * @param  array{
     *     ro: Collection<int|string, CustomerDecisionSchedule>,
     *     customer: Collection<int|string, CustomerDecisionSchedule>,
     * }  $scheduleMap
     */
    private function effectiveSchedule(RepairOrder $repairOrder, array $scheduleMap): ?CustomerDecisionSchedule
    {
        $byRepairOrder = $scheduleMap['ro']->get($repairOrder->id);

        if ($byRepairOrder instanceof CustomerDecisionSchedule) {
            return $byRepairOrder;
        }

        if ($repairOrder->customer_id === null) {
            return null;
        }

        $byCustomer = $scheduleMap['customer']->get($repairOrder->customer_id);

        return $byCustomer instanceof CustomerDecisionSchedule ? $byCustomer : null;
    }

    private function isPaid(RepairOrder $repairOrder): bool
    {
        return $repairOrder->isPaid();
    }

    private function isAwaitingCustomerDecision(RepairOrder $repairOrder): bool
    {
        return $repairOrder->status->is(RepairOrderStatus::WaitingApproval);
    }

    private function isApprovedWorkStalled(RepairOrder $repairOrder): bool
    {
        return $repairOrder->status->isOneOf([
            RepairOrderStatus::Approved,
            RepairOrderStatus::InProgress,
            RepairOrderStatus::QualityCheck,
            RepairOrderStatus::Completed,
            RepairOrderStatus::Invoiced,
            RepairOrderStatus::ReadyPickup,
        ]);
    }

    private function hasEstimateSent(RepairOrder $repairOrder): bool
    {
        if ($repairOrder->relationLoaded('communicationEvents')) {
            return $repairOrder->communicationEvents->contains(
                fn (CommunicationEvent $event): bool => $event->event_type === OperationalCommunicationType::EstimateSent,
            );
        }

        return $repairOrder->communicationEvents()
            ->where('event_type', OperationalCommunicationType::EstimateSent)
            ->exists();
    }

    private function estimateReadyAt(RepairOrder $repairOrder): Carbon
    {
        $firstLineAt = $repairOrder->lines->min('created_at');

        if ($firstLineAt instanceof Carbon) {
            return $firstLineAt;
        }

        return $repairOrder->estimate_version_at ?? $repairOrder->displayOpenedAt();
    }

    private function customerDecisionReferenceAt(RepairOrder $repairOrder, bool $hasEstimateSent): Carbon
    {
        if ($hasEstimateSent) {
            $sentAt = $this->latestEventAt($repairOrder, OperationalCommunicationType::EstimateSent);

            if ($sentAt !== null) {
                return $sentAt;
            }
        }

        return $this->estimateReadyAt($repairOrder);
    }

    private function approvedWorkReferenceAt(RepairOrder $repairOrder): Carbon
    {
        return $repairOrder->displayClosedAt()
            ?? $repairOrder->updated_at
            ?? $repairOrder->displayOpenedAt();
    }

    private function latestEventAt(RepairOrder $repairOrder, OperationalCommunicationType $type): ?Carbon
    {
        $events = $repairOrder->relationLoaded('communicationEvents')
            ? $repairOrder->communicationEvents
            : collect();

        return $events
            ->filter(fn (CommunicationEvent $event): bool => $event->event_type === $type)
            ->sortByDesc(fn (CommunicationEvent $event) => $event->occurred_at?->timestamp ?? 0)
            ->first()
            ?->occurred_at;
    }

    private function latestOutboundContactAt(RepairOrder $repairOrder): ?Carbon
    {
        $events = $repairOrder->relationLoaded('communicationEvents')
            ? $repairOrder->communicationEvents
            : collect();

        return $events
            ->filter(fn (CommunicationEvent $event): bool => $event->direction === OperationalCommunicationDirection::Outbound)
            ->sortByDesc(fn (CommunicationEvent $event) => $event->occurred_at?->timestamp ?? 0)
            ->first()
            ?->occurred_at;
    }

    private function customerDecisionDetail(RepairOrder $repairOrder, bool $hasEstimateSent): string
    {
        $lastOutbound = $this->latestOutboundContactAt($repairOrder);

        if ($lastOutbound === null) {
            return $hasEstimateSent
                ? 'Estimate sent · no follow-up recorded'
                : 'No outbound contact recorded';
        }

        return 'Last shop contact: '.$this->relativeActivityAge($lastOutbound);
    }

    private function lastCustomerActivity(RepairOrder $repairOrder, bool $hasEstimateSent): ?string
    {
        $events = $repairOrder->relationLoaded('communicationEvents')
            ? $repairOrder->communicationEvents
            : collect();

        $latestInbound = $events
            ->filter(fn (CommunicationEvent $event): bool => $event->direction === OperationalCommunicationDirection::Inbound
                || $event->event_type === OperationalCommunicationType::EstimateViewed)
            ->sortByDesc(fn (CommunicationEvent $event) => $event->occurred_at?->timestamp ?? 0)
            ->first();

        if ($latestInbound instanceof CommunicationEvent) {
            return match ($latestInbound->event_type) {
                OperationalCommunicationType::EstimateViewed => 'Viewed estimate '.$this->relativeActivityAge($latestInbound->occurred_at),
                OperationalCommunicationType::CustomerReply => 'Customer replied '.$this->relativeActivityAge($latestInbound->occurred_at),
                default => 'Last customer contact '.$this->relativeActivityAge($latestInbound->occurred_at),
            };
        }

        if ($hasEstimateSent) {
            return 'Never viewed estimate';
        }

        return null;
    }

    private function relativeActivityAge(?Carbon $at): string
    {
        if (! $at instanceof Carbon) {
            return 'unknown time';
        }

        $minutes = max(0, (int) $at->diffInMinutes(now()));

        if ($minutes < 1) {
            return 'just now';
        }

        if ($minutes < 60) {
            return $minutes.' minute'.($minutes === 1 ? '' : 's').' ago';
        }

        $hours = max(0, (int) $at->diffInHours(now()));

        if ($hours < 24) {
            return $hours.' hour'.($hours === 1 ? '' : 's').' ago';
        }

        $days = max(0, (int) $at->diffInDays(now()));

        if ($days === 0) {
            return 'today';
        }

        if ($days === 1) {
            return 'yesterday';
        }

        return $days.' days ago';
    }

    private function approvedWorkDetail(RepairOrder $repairOrder): string
    {
        return match ($repairOrder->status) {
            RepairOrderStatus::Completed,
            RepairOrderStatus::Invoiced,
            RepairOrderStatus::ReadyPickup => 'Completed · No payment',
            RepairOrderStatus::Approved => 'Approved · Not scheduled',
            RepairOrderStatus::InProgress => 'In progress · No payment',
            RepairOrderStatus::QualityCheck => 'Quality check · No payment',
            default => 'Approved work · No payment',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRow(
        string $kind,
        RepairOrder $repairOrder,
        int $dollarsAtRiskCents,
        Carbon $referenceAt,
        string $detail,
        ?CustomerDecisionSchedule $schedule = null,
        ?string $lastCustomerActivity = null,
        ?string $ageContext = null,
    ): array {
        $customer = trim(collect([
            $repairOrder->customer?->first_name,
            $repairOrder->customer?->last_name,
        ])->filter()->implode(' '));

        $vehicle = trim(collect([
            $repairOrder->vehicle?->year,
            $repairOrder->vehicle?->make,
            $repairOrder->vehicle?->model,
        ])->filter()->implode(' '));

        $detailLine = $detail;

        if ($schedule instanceof CustomerDecisionSchedule && ! $schedule->isSnoozed()) {
            $detailLine = $schedule->scheduledForLabel().($schedule->notes ? ' · '.$schedule->notes : '');
        } elseif ($schedule instanceof CustomerDecisionSchedule) {
            $detailLine = 'Returns '.$schedule->reminderStartsOn()->format('D M j').' for reminder'.($schedule->notes ? ' · '.$schedule->notes : '');
        }

        return [
            'kind' => $kind,
            'repair_order_id' => $repairOrder->repair_order_id,
            'repair_order_shop_number' => $repairOrder->repair_order_id,
            'customer_name' => $customer !== '' ? $customer : 'Unknown customer',
            'vehicle_label' => $vehicle !== '' ? $vehicle : 'Vehicle pending',
            'customer_id' => $repairOrder->customer_id,
            'vehicle_id' => $repairOrder->vehicle_id,
            'callback_phone' => PhoneNumber::normalize($repairOrder->customer?->phone),
            'customer_url' => $repairOrder->customer_id !== null
                ? route('operations.customers.show', $repairOrder->customer_id)
                : null,
            'text_url' => $repairOrder->customer_id !== null
                ? route('operations.customers.show', $repairOrder->customer_id).'?compose=text#customer-communication'
                : null,
            'dollars_at_risk_cents' => $dollarsAtRiskCents,
            'dollars_at_risk_label' => '$'.number_format($dollarsAtRiskCents / 100, 0),
            'age_days' => max(0, (int) $referenceAt->diffInDays(now())),
            'age_label' => $this->ageLabel($referenceAt),
            'age_context' => $ageContext,
            'detail' => $detailLine,
            'last_customer_activity' => $lastCustomerActivity,
            'url' => Route::has('operations.repair-orders.show')
                ? route('operations.repair-orders.show', $repairOrder)
                : '#',
            'schedule' => $this->presentSchedule($schedule, $repairOrder),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentSchedule(?CustomerDecisionSchedule $schedule, RepairOrder $repairOrder): ?array
    {
        if (! $schedule instanceof CustomerDecisionSchedule) {
            return [
                'store_url' => route('operations.work.decision-schedules.store'),
                'repair_order_shop_number' => $repairOrder->repair_order_id,
                'customer_name' => trim(collect([
                    $repairOrder->customer?->first_name,
                    $repairOrder->customer?->last_name,
                ])->filter()->implode(' ')),
            ];
        }

        return [
            'id' => $schedule->id,
            'scheduled_for' => $schedule->scheduled_for->toDateString(),
            'scheduled_for_label' => $schedule->scheduledForLabel(),
            'reminder_starts_on' => $schedule->reminderStartsOn()->toDateString(),
            'is_snoozed' => $schedule->isSnoozed(),
            'is_reminder' => ! $schedule->isSnoozed(),
            'scope' => $schedule->repair_order_id !== null ? 'repair_order' : 'customer',
            'notes' => $schedule->notes,
            'created_by_user_id' => $schedule->created_by_user_id,
            'assigned_to_label' => $schedule->creator?->name ?? 'Shop',
            'clear_url' => route('operations.work.decision-schedules.clear', $schedule),
            'store_url' => route('operations.work.decision-schedules.store'),
            'repair_order_shop_number' => $repairOrder->repair_order_id,
            'customer_name' => trim(collect([
                $repairOrder->customer?->first_name,
                $repairOrder->customer?->last_name,
            ])->filter()->implode(' ')),
        ];
    }

    private function ageLabel(Carbon $referenceAt, string $prefix = ''): string
    {
        $days = max(0, (int) $referenceAt->diffInDays(now()));

        if ($days === 0) {
            return trim($prefix.'Today');
        }

        if ($days === 1) {
            return trim($prefix.'1 day');
        }

        return trim($prefix.$days.' days');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortRowsForViewer(array $rows, ?int $viewerUserId): array
    {
        usort($rows, function (array $left, array $right) use ($viewerUserId): int {
            if ($viewerUserId !== null) {
                $leftMine = $this->rowOwnedByViewer($left, $viewerUserId) ? 0 : 1;
                $rightMine = $this->rowOwnedByViewer($right, $viewerUserId) ? 0 : 1;

                if ($leftMine !== $rightMine) {
                    return $leftMine <=> $rightMine;
                }
            }

            return ($right['dollars_at_risk_cents'] ?? 0) <=> ($left['dollars_at_risk_cents'] ?? 0);
        });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortScheduledLaterForViewer(array $rows, ?int $viewerUserId): array
    {
        usort($rows, function (array $left, array $right) use ($viewerUserId): int {
            if ($viewerUserId !== null) {
                $leftMine = $this->rowOwnedByViewer($left, $viewerUserId) ? 0 : 1;
                $rightMine = $this->rowOwnedByViewer($right, $viewerUserId) ? 0 : 1;

                if ($leftMine !== $rightMine) {
                    return $leftMine <=> $rightMine;
                }
            }

            $leftDate = $left['schedule']['scheduled_for'] ?? '9999-12-31';
            $rightDate = $right['schedule']['scheduled_for'] ?? '9999-12-31';

            return $leftDate <=> $rightDate;
        });

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowOwnedByViewer(array $row, int $viewerUserId): bool
    {
        $schedule = $row['schedule'] ?? null;

        if (! is_array($schedule)) {
            return false;
        }

        return isset($schedule['created_by_user_id'])
            && (int) $schedule['created_by_user_id'] === $viewerUserId;
    }
}
