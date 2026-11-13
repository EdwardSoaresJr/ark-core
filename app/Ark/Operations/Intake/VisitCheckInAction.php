<?php

namespace App\Ark\Operations\Intake;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Check In / New visit for a selected customer visit context.
 *
 * Reuses {@see route('operations.intake.create')} / lead intake — never a separate intake path.
 * An open RO means that visit already has a repair order; that is not the same as RO workflow
 * status (approval, diagnosis, etc.). Arrival without an RO still needs Check In.
 */
final class VisitCheckInAction
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     key: string,
     *     label: string,
     *     url: string,
     *     style: 'primary'|'secondary',
     *     hint: string|null,
     * }|null
     */
    public function forContext(array $context, ?User $viewer): ?array
    {
        if ($viewer === null || ! $viewer->can(ArkCapability::RepairOrdersManage->value)) {
            return null;
        }

        if (! Route::has('operations.intake.create')) {
            return null;
        }

        $customerId = (int) (data_get($context, 'customer.id') ?: data_get($context, 'customer_id') ?: 0);
        $customer = $customerId > 0 ? Customer::query()->find($customerId) : null;
        $lead = $this->leadFromContext($context);
        $phone = $this->phoneFromContext($context);
        $vehicleId = $this->vehicleIdFromContext($context);
        $openVisitRoId = $this->openVisitRepairOrderId($context, $customer, $vehicleId);
        $appointment = $this->checkInAppointment($customer, $openVisitRoId, $context, $vehicleId);

        $params = $this->intakeParams($customer, $lead, $phone, $vehicleId, $appointment);
        if ($params === null && $customer === null && $lead === null && $phone === null) {
            // Still allow a blank Check In when the thread has no match — same as the toolbar.
            $params = [];
        }

        if ($params === null) {
            return null;
        }

        // Prefer the shared intake.create URL with query prefills so appointment/vehicle/concern
        // survive. Lead intake is only a thin redirect when we have nothing richer to carry.
        $url = match (true) {
            $appointment !== null,
            $customer !== null,
            $vehicleId !== null,
            filled($params['concern'] ?? null),
            filled($params['appointment_id'] ?? null) => route('operations.intake.create', $params),
            $lead !== null && $lead->isOpen() && Route::has('operations.leads.intake') => route('operations.leads.intake', $lead),
            default => route('operations.intake.create', $params),
        };
        if ($openVisitRoId !== null) {
            return [
                'key' => 'new_visit',
                'label' => 'New visit',
                'url' => $url,
                'style' => 'secondary',
                'hint' => 'This visit already has an open repair order. Use New visit only for a different visit.',
            ];
        }

        return [
            'key' => 'check_in',
            'label' => 'Check In',
            'url' => $url,
            'style' => 'primary',
            'hint' => $appointment !== null && $appointment->status === AppointmentStatus::Arrived
                ? 'Customer arrived. Check In opens the repair order for this visit.'
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function leadFromContext(array $context): ?Lead
    {
        $leadId = (int) ($context['lead_id'] ?? 0);

        return $leadId > 0 ? Lead::query()->find($leadId) : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function phoneFromContext(array $context): ?string
    {
        $raw = data_get($context, 'thread.normalized_phone')
            ?? data_get($context, 'thread.phone')
            ?? data_get($context, 'phone')
            ?? data_get($context, 'customer.phone');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return PhoneNumber::normalize($raw) ?? trim($raw);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function vehicleIdFromContext(array $context): ?int
    {
        $vehicleId = (int) (data_get($context, 'current_visit.vehicle_id')
            ?: data_get($context, 'vehicle.id')
            ?: data_get($context, 'vehicle_id')
            ?: 0);

        if ($vehicleId > 0) {
            return $vehicleId;
        }

        $roId = (int) (data_get($context, 'primary_ro.repair_order_id')
            ?: data_get($context, 'current_visit.repair_order_id')
            ?: 0);
        if ($roId <= 0) {
            return null;
        }

        $repairOrder = $this->findRepairOrder($roId);

        return $repairOrder?->vehicle_id !== null ? (int) $repairOrder->vehicle_id : null;
    }

    /**
     * Open RO for the selected visit — presence means check-in already created that visit's RO.
     * Do not infer this from workflow status labels. When primary visit is incomplete but the
     * rail still lists open repair orders (or open_visits), those orders must not be overlooked.
     *
     * @param  array<string, mixed>  $context
     */
    private function openVisitRepairOrderId(array $context, ?Customer $customer, ?int $vehicleId): ?int
    {
        $candidateIds = $this->openRepairOrderCandidateIds($context);

        if ($vehicleId !== null) {
            foreach ($candidateIds as $id) {
                $repairOrder = $this->findRepairOrder($id);
                if ($repairOrder !== null && (int) $repairOrder->vehicle_id === $vehicleId) {
                    return (int) $repairOrder->getKey();
                }
            }

            if ($customer !== null) {
                $forVehicle = RepairOrder::query()
                    ->where('customer_id', $customer->id)
                    ->where('vehicle_id', $vehicleId)
                    ->whereIn('status', RepairOrderStatus::operationalQueueValues())
                    ->latest('id')
                    ->first();

                if ($forVehicle !== null) {
                    return (int) $forVehicle->getKey();
                }
            }

            // A different vehicle's open RO must not block Check In for this vehicle.
            return null;
        }

        foreach ($candidateIds as $id) {
            $repairOrder = $this->findRepairOrder($id);
            if ($repairOrder !== null) {
                return (int) $repairOrder->getKey();
            }
        }

        $primaryIncomplete = (int) (data_get($context, 'primary_ro.repair_order_id') ?: 0) <= 0
            && (int) (data_get($context, 'current_visit.repair_order_id') ?: 0) <= 0;
        $listedOpenOrders = is_array($context['open_repair_orders'] ?? null) ? $context['open_repair_orders'] : [];
        $openVisits = is_array(data_get($context, 'current_visit.open_visits'))
            ? data_get($context, 'current_visit.open_visits')
            : [];

        if ($primaryIncomplete && $customer !== null && ($listedOpenOrders !== [] || $openVisits !== [])) {
            $open = RepairOrder::query()
                ->where('customer_id', $customer->id)
                ->whereIn('status', RepairOrderStatus::operationalQueueValues())
                ->latest('id')
                ->first();

            if ($open !== null) {
                return (int) $open->getKey();
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<int>
     */
    private function openRepairOrderCandidateIds(array $context): array
    {
        $ids = [];

        foreach ([
            data_get($context, 'primary_ro.repair_order_id'),
            data_get($context, 'current_visit.repair_order_id'),
        ] as $raw) {
            $id = (int) ($raw ?: 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        foreach ([
            data_get($context, 'current_visit.open_visits', []),
            $context['open_repair_orders'] ?? [],
        ] as $rows) {
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = (int) ($row['repair_order_id'] ?? $row['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function findRepairOrder(int $id): ?RepairOrder
    {
        return RepairOrder::query()->whereKey($id)->first()
            ?? RepairOrder::query()->where('repair_order_id', $id)->first();
    }

    /**
     * Prefill the appointment that belongs to this visit — never another vehicle's appointment.
     *
     * @param  array<string, mixed>  $context
     */
    private function checkInAppointment(
        ?Customer $customer,
        ?int $openVisitRoId,
        array $context,
        ?int $vehicleId,
    ): ?Appointment {
        if ($customer === null || $openVisitRoId !== null) {
            return null;
        }

        $statuses = [
            AppointmentStatus::Scheduled->value,
            AppointmentStatus::Confirmed->value,
            AppointmentStatus::Arrived->value,
        ];

        $explicitId = (int) (data_get($context, 'appointment_id')
            ?: data_get($context, 'appointment.id')
            ?: data_get($context, 'current_visit.appointment_id')
            ?: 0);

        if ($explicitId > 0) {
            $explicit = Appointment::query()
                ->whereKey($explicitId)
                ->where('customer_id', $customer->id)
                ->whereNull('repair_order_id')
                ->whereIn('status', $statuses)
                ->first();

            if ($explicit !== null
                && ($vehicleId === null
                    || $explicit->vehicle_id === null
                    || (int) $explicit->vehicle_id === $vehicleId)) {
                return $explicit;
            }
        }

        $query = Appointment::query()
            ->where('customer_id', $customer->id)
            ->whereNull('repair_order_id')
            ->whereIn('status', $statuses);

        if ($vehicleId !== null) {
            $query->where(function ($builder) use ($vehicleId): void {
                $builder->where('vehicle_id', $vehicleId)->orWhereNull('vehicle_id');
            })->orderByRaw('CASE WHEN vehicle_id = ? THEN 0 ELSE 1 END', [$vehicleId]);
        }

        $leadId = (int) ($context['lead_id'] ?? 0);
        if ($leadId > 0) {
            $query->orderByRaw('CASE WHEN lead_id = ? THEN 0 ELSE 1 END', [$leadId]);
        }

        return $query
            ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', [
                AppointmentStatus::Arrived->value,
                AppointmentStatus::Confirmed->value,
            ])
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * @return array<string, int|string>|null
     */
    private function intakeParams(
        ?Customer $customer,
        ?Lead $lead,
        ?string $phone,
        ?int $vehicleId,
        ?Appointment $appointment,
    ): ?array {
        if ($appointment !== null) {
            return IntakeEntryQuery::fromAppointment($appointment);
        }

        if ($lead !== null && $lead->isOpen()) {
            $params = IntakeEntryQuery::fromLead($lead);
            if ($customer !== null) {
                $params['customer_id'] = $customer->id;
            }
            if ($vehicleId !== null) {
                $params['vehicle_id'] = $vehicleId;
            }

            return $params;
        }

        if ($customer !== null) {
            $params = ['customer_id' => $customer->id];
            if ($vehicleId !== null) {
                $params['vehicle_id'] = $vehicleId;
            }
            if ($lead !== null) {
                $params['lead_id'] = $lead->id;
                $concern = trim((string) $lead->concern);
                if ($concern !== '') {
                    $params['concern'] = mb_substr($concern, 0, 5000);
                }
            }

            return $params;
        }

        if ($phone !== null) {
            $params = IntakeEntryQuery::fromInboundPhoneMessage($phone, '');
            if ($lead !== null) {
                $params['lead_id'] = $lead->id;
                $concern = trim((string) $lead->concern);
                if ($concern !== '') {
                    $params['concern'] = mb_substr($concern, 0, 5000);
                }
            }

            return $params;
        }

        if ($lead !== null && $lead->isOpen()) {
            return IntakeEntryQuery::fromLead($lead);
        }

        return null;
    }
}
