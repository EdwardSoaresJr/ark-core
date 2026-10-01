<?php

namespace App\Ark\Operations\Today\Surface;

use App\Ark\Operations\Appointments\TodayAppointmentsProjection;
use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Today\Lifecycle\TodayLifecycleComposer;
use App\Ark\Operations\Today\Lifecycle\TodayRecommendationKind;
use App\Ark\Operations\Workboard\WorkboardLens;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Single operational composition path for Today.
 *
 * Advisor / Owner share the same section shape; Owner widens ownership labels.
 * Technician composes assigned production work only.
 */
final class TodayOperationalComposer
{
    private const NEEDS_ATTENTION_CAP = 5;

    private const PARTS_PRODUCTION_CAP = 3;

    private const APPOINTMENTS_CAP = 3;

    private const TECH_SECTION_CAP = 3;

    public function __construct(
        private readonly TodayBriefingMapper $mapper,
        private readonly TodayOwnerResolver $owners,
        private readonly TodayAppointmentsProjection $appointments,
        private readonly TodayLifecycleComposer $lifecycle,
    ) {}

    /**
     * @return list<TodaySection>
     */
    public function forAdvisor(BriefingContext $context, User $user): array
    {
        return $this->composeStaffOperations($context, $user, TodayLens::Advisor);
    }

    /**
     * @return list<TodaySection>
     */
    public function forOwner(BriefingContext $context, User $user): array
    {
        return $this->composeStaffOperations($context, $user, TodayLens::Owner);
    }

    /**
     * @return list<TodaySection>
     */
    public function forTechnician(User $user): array
    {
        $repairOrders = RepairOrder::query()
            ->with(['customer', 'vehicle', 'assignedTechnician'])
            ->whereIn('status', WorkboardLens::queueStatusValues(WorkboardLens::TECHNICIAN))
            ->get();

        $repairOrders = WorkboardLens::filterRepairOrders($repairOrders, WorkboardLens::TECHNICIAN, $user);
        $techLabel = $this->owners->forUser($user);
        $workboardUrl = route('operations.workboard');

        $sections = [];

        $assigned = $this->technicianActions(
            $repairOrders,
            [RepairOrderStatus::InProgress],
            $user,
            $techLabel,
            fn (RepairOrder $ro): string => $this->vehicleLabel($ro),
            'assigned',
        );
        if ($assigned['visible'] !== []) {
            $sections[] = $this->cappedSection(
                'assigned_work',
                'Assigned work',
                $assigned['all'],
                self::TECH_SECTION_CAP,
                $workboardUrl,
            );
        }

        $blocked = $this->technicianActions(
            $repairOrders,
            [RepairOrderStatus::WaitingParts],
            $user,
            $techLabel,
            fn (RepairOrder $ro): string => 'Blocked on parts · '.$this->vehicleLabel($ro),
            'blocked',
        );
        if ($blocked['visible'] !== []) {
            $sections[] = $this->cappedSection(
                'blocked_jobs',
                'Blocked jobs',
                $blocked['all'],
                self::TECH_SECTION_CAP,
                $workboardUrl,
            );
        }

        $ready = $this->technicianActions(
            $repairOrders,
            [RepairOrderStatus::Approved, RepairOrderStatus::ReadyForWork],
            $user,
            $techLabel,
            fn (RepairOrder $ro): string => 'Ready to start · '.$this->vehicleLabel($ro),
            'ready',
        );
        if ($ready['visible'] !== []) {
            $sections[] = $this->cappedSection(
                'parts_received',
                'Parts received',
                $ready['all'],
                self::TECH_SECTION_CAP,
                $workboardUrl,
            );
        }

        $qc = $this->technicianActions(
            $repairOrders,
            [RepairOrderStatus::QualityCheck],
            $user,
            $techLabel,
            fn (RepairOrder $ro): string => 'Inspection follow-up · '.$this->vehicleLabel($ro),
            'qc',
        );
        if ($qc['visible'] !== []) {
            $sections[] = $this->cappedSection(
                'inspection_followups',
                'Inspection follow-ups',
                $qc['all'],
                self::TECH_SECTION_CAP,
                $workboardUrl,
            );
        }

        return $sections;
    }

    /**
     * @return list<TodaySection>
     */
    private function composeStaffOperations(BriefingContext $context, User $user, TodayLens $lens): array
    {
        $advisorLabel = $lens === TodayLens::Owner
            ? $this->owners->defaultAdvisorLabel()
            : $this->owners->forUser($user);

        $lifecycleBySection = $this->lifecycle->actionsBySection($context, $lens);
        $sections = [];

        $needsAttention = $this->actionable(array_merge(
            $lifecycleBySection['customer_approvals'] ?? [],
            array_map(
                fn ($item) => $this->mapper->toAction($item, $advisorLabel),
                $this->mapper->items($context, ['large_estimate_aging'], 20),
            ),
            array_map(
                fn ($item) => $this->mapper->toAction($item, $advisorLabel),
                $this->mapper->items($context, ['missed_call', 'customer_waiting'], 20),
            ),
        ));

        if ($needsAttention !== []) {
            $viewAll = $user->can(ArkCapability::OperationsAccess->value)
                ? CommunicationsNeedsYou::url()
                : route('operations.today');

            $sections[] = $this->cappedSection(
                'needs_attention',
                'Needs Attention',
                $needsAttention,
                self::NEEDS_ATTENTION_CAP,
                $viewAll,
            );
        }

        $partsArrivalRoIds = $this->lifecycleRepairOrderIds(
            $lifecycleBySection['waiting_parts'] ?? [],
            TodayRecommendationKind::PartsArrival,
        );

        $parts = $this->actionable(array_merge(
            $lifecycleBySection['waiting_parts'] ?? [],
            array_map(
                fn ($item) => $this->mapper->toAction($item, $advisorLabel),
                array_values(array_filter(
                    $this->mapper->items($context, ['waiting_parts'], 20),
                    fn ($item) => ! in_array((int) ($item->repairOrderId ?? 0), $partsArrivalRoIds, true),
                )),
            ),
        ));

        if ($parts !== []) {
            $viewAll = $user->can(ArkCapability::OperationsAccess->value)
                ? route('operations.index')
                : route('operations.workboard');

            $sections[] = $this->cappedSection(
                'parts_production',
                'Parts / Production',
                $parts,
                self::PARTS_PRODUCTION_CAP,
                $viewAll,
            );
        }

        $appointmentActions = $this->actionable($this->appointmentActions($user, $advisorLabel));
        if ($appointmentActions !== []) {
            $sections[] = $this->cappedSection(
                'appointments',
                'Next Appointments',
                $appointmentActions,
                self::APPOINTMENTS_CAP,
                route('operations.appointments.index'),
                'View Schedule',
            );
        }

        return $sections;
    }

    /**
     * @param  list<TodayAction>  $actions
     * @return list<TodayAction>
     */
    private function actionable(array $actions): array
    {
        $seen = [];
        $filtered = [];

        foreach ($actions as $action) {
            if (! $action instanceof TodayAction) {
                continue;
            }

            if (trim($action->url) === '') {
                continue;
            }

            if (isset($seen[$action->key])) {
                continue;
            }

            $seen[$action->key] = true;
            $filtered[] = $action;
        }

        return $filtered;
    }

    /**
     * @param  list<TodayAction>  $actions
     */
    private function cappedSection(
        string $key,
        string $title,
        array $actions,
        int $cap,
        string $viewAllUrl,
        string $viewAllLabel = 'View all',
    ): TodaySection {
        $total = count($actions);

        return new TodaySection(
            key: $key,
            title: $title,
            actions: array_slice($actions, 0, $cap),
            totalCount: $total,
            viewAllUrl: $viewAllUrl,
            viewAllLabel: $viewAllLabel,
        );
    }

    /**
     * @return list<TodayAction>
     */
    private function appointmentActions(User $user, string $ownerLabel): array
    {
        $projection = $this->appointments->resolve(viewer: $user);
        $actions = [];

        foreach ($projection['rows'] ?? [] as $row) {
            $url = (string) ($row['show_url'] ?? '');
            if ($url === '') {
                continue;
            }

            $customer = (string) ($row['customer_name'] ?? 'Customer');
            $time = (string) ($row['time_label'] ?? '');

            $actions[] = new TodayAction(
                key: 'appointment_'.$row['id'],
                title: trim($customer.($time !== '' ? ' · '.$time : '')),
                ownerLabel: $ownerLabel,
                url: $url,
                whyYouLabel: 'You are the scheduled advisor.',
                expectedOutcome: 'Customer arrives prepared and on time.',
                reason: (string) ($row['status_label'] ?? 'Scheduled today'),
            );
        }

        return $actions;
    }

    /**
     * @param  list<TodayAction>  $actions
     * @return list<int>
     */
    private function lifecycleRepairOrderIds(array $actions, TodayRecommendationKind $kind): array
    {
        $prefix = $kind->value.'_';

        return array_values(array_filter(array_map(
            static function (TodayAction $action) use ($prefix): ?int {
                if (! str_starts_with($action->key, $prefix)) {
                    return null;
                }

                $id = substr($action->key, strlen($prefix));

                return is_numeric($id) ? (int) $id : null;
            },
            $actions,
        )));
    }

    /**
     * @param  list<RepairOrderStatus>  $statuses
     * @return array{all: list<TodayAction>, visible: list<TodayAction>}
     */
    private function technicianActions(
        Collection $repairOrders,
        array $statuses,
        User $user,
        string $ownerLabel,
        callable $title,
        string $keyPrefix,
    ): array {
        $slugValues = array_map(
            static fn (RepairOrderStatus $status): string => $status->value,
            $statuses,
        );

        $actions = [];

        foreach ($repairOrders as $repairOrder) {
            if (! in_array($repairOrder->workboardLaneStatus()->value, $slugValues, true)) {
                continue;
            }

            if ($repairOrder->assigned_technician_id !== null
                && $repairOrder->assigned_technician_id !== $user->id
                && $repairOrder->workboardLaneStatus()->is(RepairOrderStatus::InProgress)) {
                continue;
            }

            $url = route('operations.repair-orders.show', $repairOrder);
            if ($url === '') {
                continue;
            }

            $actions[] = new TodayAction(
                key: $keyPrefix.'_'.$repairOrder->repair_order_id,
                title: $title($repairOrder),
                ownerLabel: $ownerLabel,
                url: $url,
                whyYouLabel: $repairOrder->assigned_technician_id === $user->id
                    ? 'This job is assigned to you.'
                    : 'You are responsible for production on this RO.',
                expectedOutcome: match ($keyPrefix) {
                    'assigned' => 'Complete assigned production work.',
                    'blocked' => 'Clear the blocker so work can resume.',
                    'ready' => 'Technician can resume work.',
                    'qc' => 'Close the loop on inspection findings.',
                    default => 'Keep assigned work moving.',
                },
                reason: 'RO #'.$repairOrder->repair_order_id,
            );
        }

        return [
            'all' => $actions,
            'visible' => array_slice($actions, 0, self::TECH_SECTION_CAP),
        ];
    }

    private function vehicleLabel(RepairOrder $repairOrder): string
    {
        $vehicle = $repairOrder->vehicle;

        if ($vehicle === null) {
            return 'RO #'.$repairOrder->repair_order_id;
        }

        return trim(implode(' ', array_filter([
            $vehicle->year,
            $vehicle->make,
            $vehicle->model,
        ])));
    }
}
