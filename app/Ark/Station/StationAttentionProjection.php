<?php

namespace App\Ark\Station;

use App\Ark\Dragon\DragonWorkProjection;
use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentKind;
use App\Ark\Operations\Appointments\AppointmentsBoardProjection;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\RepairOrders\ApprovalForecastProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Deterministic Shop Glass attention queue. ARK decides who qualifies.
 * Dragon may rank/explain this set. Dragon never invents members.
 */
final class StationAttentionProjection
{
    public const ROW_LIMIT = 12;

    public const STALE_AFTER_DAYS = 3;

    public const OLD_WAITING_PARTS_DAYS = 14;

    public const VERY_OLD_WAITING_PARTS_DAYS = 90;

    public const HIGH_WAITING_APPROVAL_CENTS = 100_000;

    public const CRITICAL_WAITING_APPROVAL_CENTS = 150_000;

    public const CRITICAL_WAITING_APPROVAL_DAYS = 21;

    public function __construct(
        private readonly ApprovalForecastProjection $approvals,
        private readonly AppointmentsBoardProjection $appointments,
        private readonly DragonWorkProjection $work,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(?Carbon $now = null): array
    {
        $now = $now?->copy() ?? ShopDisplayTimezone::now();

        $openOrders = RepairOrder::query()
            ->with([
                'vehicle:id,year,make,model,trim',
                'assignedTechnician:id,name',
                'customer:id,first_name,last_name',
            ])
            ->whereNotIn('status', $this->closedStatuses())
            ->orderByRaw('COALESCE(opened_at, created_at) ASC')
            ->get();

        $appointmentsByRoId = $this->activeAppointmentsIndexedByRepairOrderId($now);
        $production = $this->work->productionStatusSlugs();

        $rows = [];
        $waitingApprovalCents = 0;
        $waitingApprovalCount = 0;
        $waitingPartsCount = 0;
        $inProductionCount = 0;
        $unassignedCount = 0;

        foreach ($openOrders as $repairOrder) {
            $status = $repairOrder->status;
            if ($status->is(RepairOrderStatus::WaitingApproval)) {
                $waitingApprovalCount++;
            }
            if ($status->is(RepairOrderStatus::WaitingParts)) {
                $waitingPartsCount++;
            }
            if (in_array($status->value, $production, true)) {
                $inProductionCount++;
            }
            if ($this->technicianLabel($repairOrder) === null) {
                $unassignedCount++;
            }

            $pendingCents = 0;
            if ($status->is(RepairOrderStatus::WaitingApproval)) {
                $pendingCents = (int) $this->approvals->for($repairOrder)['pending_cents'];
                $waitingApprovalCents += $pendingCents;
            }

            $row = $this->maybeRow(
                $repairOrder,
                $pendingCents,
                $appointmentsByRoId->get($repairOrder->id),
                $now,
            );
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        usort($rows, function (array $left, array $right): int {
            return [$right['attention_severity'], $right['waiting_approval_amount_cents'] ?? 0, $right['age_days']]
                <=> [$left['attention_severity'], $left['waiting_approval_amount_cents'] ?? 0, $left['age_days']];
        });

        $rows = array_slice($rows, 0, self::ROW_LIMIT);
        $comingIn = $this->compactComingIn($now);

        $shopSummary = [
            'open' => $openOrders->count(),
            'waiting_approval' => $waitingApprovalCount,
            'in_production' => $inProductionCount,
            'waiting_parts' => $waitingPartsCount,
            'unassigned' => $unassignedCount,
            'coming_in' => count($comingIn),
            'waiting_approval_amount_cents' => $waitingApprovalCents,
            'waiting_approval_amount_label' => $this->moneyLabel($waitingApprovalCents),
            'money_semantics' => 'waiting_approval_amount is Approval Forecast pending (recommended) cents on ROs currently waiting approval. Not posted sales, cash collected, or profit.',
        ];

        $snapshot = [
            'shop_summary' => [
                'open' => $shopSummary['open'],
                'waiting_approval' => $shopSummary['waiting_approval'],
                'in_production' => $shopSummary['in_production'],
                'waiting_parts' => $shopSummary['waiting_parts'],
                'unassigned' => $shopSummary['unassigned'],
                'coming_in' => $shopSummary['coming_in'],
                'waiting_approval_amount' => $shopSummary['waiting_approval_amount_label'],
                'money_semantics' => 'waiting_approval_amount is already US dollars of Approval Forecast pending (recommended) on ROs waiting approval. Not cents. Not posted sales, cash, or profit.',
            ],
            'rows' => array_map(fn (array $row): array => $this->nudgeRow($row), $rows),
            'coming_in' => $comingIn,
        ];

        return [
            'rows' => $rows,
            'shop_summary' => $shopSummary,
            'coming_in' => $comingIn,
            'snapshot' => $snapshot,
            'snapshot_fingerprint' => sha1((string) json_encode($snapshot)),
            'row_limit' => self::ROW_LIMIT,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function maybeRow(RepairOrder $repairOrder, int $pendingCents, mixed $appointments, Carbon $now): ?array
    {
        $linked = $appointments instanceof Collection
            ? $appointments->all()
            : (is_array($appointments) ? $appointments : []);
        $status = $repairOrder->status;
        $ageDays = (int) $repairOrder->displayOpenedAt()->diffInDays($now);
        $updatedDays = (int) ($repairOrder->updated_at ?? $repairOrder->displayOpenedAt())->diffInDays($now);
        $technician = $this->technicianLabel($repairOrder);
        $unassigned = $technician === null;
        $todayAppointment = $this->todayAppointment($linked, $now);
        $returnAppointment = $this->upcomingReturn($linked, $now);
        $reasons = [];

        if ($status->is(RepairOrderStatus::WaitingApproval)) {
            $reasons[] = 'waiting_approval';
            if ($pendingCents >= self::HIGH_WAITING_APPROVAL_CENTS) {
                $reasons[] = 'high_waiting_approval_value';
            }
        }

        if ($status->is(RepairOrderStatus::WaitingParts) && $ageDays >= self::OLD_WAITING_PARTS_DAYS) {
            $reasons[] = 'old_waiting_parts';
        }

        if ($unassigned && $status->isOneOf([
            RepairOrderStatus::WaitingApproval,
            RepairOrderStatus::WaitingParts,
            RepairOrderStatus::Approved,
            RepairOrderStatus::ReadyForWork,
            RepairOrderStatus::InProgress,
            RepairOrderStatus::Estimate,
        ])) {
            $reasons[] = 'unassigned';
        }

        if ($updatedDays >= self::STALE_AFTER_DAYS && $status->isOneOf([
            RepairOrderStatus::WaitingApproval,
            RepairOrderStatus::WaitingParts,
            RepairOrderStatus::Estimate,
        ])) {
            $reasons[] = 'stale';
        }

        if ($todayAppointment !== null && $status->isOneOf([
            RepairOrderStatus::WaitingApproval,
            RepairOrderStatus::WaitingParts,
            RepairOrderStatus::Estimate,
            RepairOrderStatus::Approved,
        ])) {
            $reasons[] = 'appointment_today';
        }

        if ($returnAppointment !== null && $status->isOneOf([
            RepairOrderStatus::WaitingParts,
            RepairOrderStatus::WaitingApproval,
        ])) {
            $reasons[] = 'return_scheduled';
        }

        $reasons = array_values(array_unique($reasons));
        if ($reasons === []) {
            return null;
        }

        $severity = $this->severity($reasons, $ageDays, $pendingCents, $unassigned);

        return [
            'repair_order_id' => $repairOrder->repair_order_id,
            'vehicle_label' => $this->vehicleLabel($repairOrder),
            'customer_label' => $this->customerLabel($repairOrder),
            'workflow_status' => $status->value,
            'status_label' => $status->label(),
            'assigned_technician' => $technician,
            'age_days' => $ageDays,
            'updated_at' => $repairOrder->updated_at?->toIso8601String(),
            'opened_at' => $repairOrder->displayOpenedAt()->toIso8601String(),
            'next_action' => $this->nextAction($reasons),
            'appointment_summary' => $this->appointmentSummary($todayAppointment ?? $returnAppointment),
            'waiting_approval_amount_cents' => $status->is(RepairOrderStatus::WaitingApproval) ? $pendingCents : null,
            'waiting_approval_amount_label' => $status->is(RepairOrderStatus::WaitingApproval) && $pendingCents > 0
                ? $this->moneyLabel($pendingCents)
                : null,
            'attention_reasons' => $reasons,
            'attention_reason' => $this->reasonLabel($reasons),
            'attention_severity' => $severity,
            'attention_severity_label' => match ($severity) {
                3 => 'critical',
                2 => 'high',
                default => 'normal',
            },
            'open_in_ark_url' => url('/app/repair-orders/'.$repairOrder->repair_order_id),
        ];
    }

    /**
     * @param  list<string>  $reasons
     */
    private function severity(array $reasons, int $ageDays, int $pendingCents, bool $unassigned): int
    {
        if (
            in_array('waiting_approval', $reasons, true)
            && ($ageDays >= self::CRITICAL_WAITING_APPROVAL_DAYS || $pendingCents >= self::CRITICAL_WAITING_APPROVAL_CENTS)
        ) {
            return 3;
        }

        if (in_array('old_waiting_parts', $reasons, true) && $ageDays >= self::VERY_OLD_WAITING_PARTS_DAYS) {
            return 3;
        }

        if (in_array('waiting_approval', $reasons, true) || in_array('old_waiting_parts', $reasons, true)) {
            return 2;
        }

        if (in_array('stale', $reasons, true) && $unassigned) {
            return 2;
        }

        return 1;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function nextAction(array $reasons): string
    {
        return match (true) {
            in_array('waiting_approval', $reasons, true) => 'Get approval',
            in_array('old_waiting_parts', $reasons, true) => 'Check parts',
            in_array('unassigned', $reasons, true) => 'Assign technician',
            in_array('appointment_today', $reasons, true) => 'Prepare for arrival',
            in_array('return_scheduled', $reasons, true) => 'Confirm return',
            default => 'Review',
        };
    }

    /**
     * @param  list<string>  $reasons
     */
    private function reasonLabel(array $reasons): string
    {
        $order = ['waiting_approval', 'old_waiting_parts', 'unassigned', 'stale', 'appointment_today', 'return_scheduled'];
        $labels = [
            'waiting_approval' => 'Waiting approval',
            'old_waiting_parts' => 'Waiting parts',
            'unassigned' => 'Unassigned',
            'stale' => 'Stale',
            'appointment_today' => 'Coming in today',
            'return_scheduled' => 'Return scheduled',
        ];

        foreach ($order as $reason) {
            if (in_array($reason, $reasons, true)) {
                return $labels[$reason];
            }
        }

        return $labels[$reasons[0] ?? ''] ?? 'Needs attention';
    }

    /**
     * @param  list<Appointment>  $appointments
     */
    private function todayAppointment(array $appointments, Carbon $now): ?Appointment
    {
        $day = $now->copy()->timezone(ShopDisplayTimezone::resolve())->toDateString();

        foreach ($appointments as $appointment) {
            if (! $appointment->status->isUpcoming()) {
                continue;
            }
            $local = $appointment->starts_at === null
                ? null
                : ShopDisplayTimezone::present($appointment->starts_at)->toDateString();
            if ($local === $day) {
                return $appointment;
            }
        }

        return null;
    }

    /**
     * @param  list<Appointment>  $appointments
     */
    private function upcomingReturn(array $appointments, Carbon $now): ?Appointment
    {
        foreach ($appointments as $appointment) {
            if (! $appointment->status->isUpcoming()) {
                continue;
            }
            if ($appointment->kind !== AppointmentKind::Return) {
                continue;
            }
            if ($appointment->starts_at !== null && $appointment->starts_at->gte($now->copy()->utc()->startOfDay())) {
                return $appointment;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Collection<int, Appointment>>
     */
    private function activeAppointmentsIndexedByRepairOrderId(Carbon $now): Collection
    {
        $from = $now->copy()->timezone(ShopDisplayTimezone::resolve())->startOfDay()->utc();

        return Appointment::query()
            ->whereNotNull('repair_order_id')
            ->where('starts_at', '>=', $from)
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Confirmed->value])
            ->orderBy('starts_at')
            ->get()
            ->groupBy('repair_order_id');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function appointmentSummary(?Appointment $appointment): ?array
    {
        if ($appointment === null) {
            return null;
        }

        $local = $appointment->starts_at !== null
            ? ShopDisplayTimezone::present($appointment->starts_at)
            : null;

        return [
            'kind' => $appointment->kind?->value,
            'kind_label' => $appointment->kind?->label(),
            'time_label' => $local?->format('g:i A'),
            'when_label' => $local?->format('D M j · g:i A'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function compactComingIn(Carbon $now): array
    {
        return array_map(static function (array $row): array {
            $kind = $row['kind'] ?? null;

            return [
                'time_label' => $row['time_label'] ?? null,
                'customer_label' => $row['customer_label'] ?? null,
                'vehicle_label' => $row['vehicle_label'] ?? null,
                'repair_order_id' => $row['repair_order_id'] ?? null,
                'kind' => $kind,
                'kind_label' => match ($kind) {
                    'return' => 'Return',
                    'follow_up' => 'Follow-up',
                    default => 'Intake',
                },
            ];
        }, $this->appointments->comingInOn($now));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function nudgeRow(array $row): array
    {
        return [
            'repair_order_id' => $row['repair_order_id'],
            'vehicle_label' => $row['vehicle_label'],
            'status_label' => $row['status_label'],
            'age_days' => $row['age_days'],
            'assigned_technician' => $row['assigned_technician'],
            'attention_reasons' => $row['attention_reasons'],
            'attention_severity' => $row['attention_severity'],
            'waiting_approval_amount' => $row['waiting_approval_amount_label'],
            'next_action' => $row['next_action'],
        ];
    }

    private function vehicleLabel(RepairOrder $repairOrder): string
    {
        $vehicle = $repairOrder->vehicle;
        if ($vehicle === null) {
            return 'Vehicle';
        }

        $year = $vehicle->year ? substr((string) $vehicle->year, -2) : '';
        $name = trim(implode(' ', array_filter([$year, $vehicle->make, $vehicle->model])));

        return $name !== '' ? $name : (string) ($vehicle->display_name ?: 'Vehicle');
    }

    private function customerLabel(RepairOrder $repairOrder): string
    {
        $name = trim((string) ($repairOrder->customer?->name ?? ''));

        return $name !== '' ? $name : 'Customer';
    }

    private function technicianLabel(RepairOrder $repairOrder): ?string
    {
        $name = trim((string) ($repairOrder->assignedTechnician?->name ?? ''));

        return $name === '' ? null : $name;
    }

    private function moneyLabel(int $cents): string
    {
        if ($cents % 100 === 0) {
            return '$'.number_format(intdiv($cents, 100));
        }

        return '$'.number_format($cents / 100, 2);
    }

    /**
     * @return list<string>
     */
    private function closedStatuses(): array
    {
        return [
            RepairOrderStatus::Closed->value,
            RepairOrderStatus::Completed->value,
            RepairOrderStatus::Invoiced->value,
            RepairOrderStatus::ReadyPickup->value,
        ];
    }
}
