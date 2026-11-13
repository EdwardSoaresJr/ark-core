<?php

namespace App\Ark\Operations\Intake;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
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
        $openVisitRoId = $this->openVisitRepairOrderId($context);
        $appointment = $this->checkInAppointment($customer, $openVisitRoId);

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
            ?: 0);

        return $vehicleId > 0 ? $vehicleId : null;
    }

    /**
     * Open RO for the selected visit — presence means check-in already created that visit's RO.
     * Do not infer this from workflow status labels.
     *
     * @param  array<string, mixed>  $context
     */
    private function openVisitRepairOrderId(array $context): ?int
    {
        $id = (int) (data_get($context, 'primary_ro.repair_order_id')
            ?: data_get($context, 'current_visit.repair_order_id')
            ?: 0);

        if ($id <= 0) {
            return null;
        }

        return RepairOrder::query()->whereKey($id)->exists() ? $id : null;
    }

    private function checkInAppointment(?Customer $customer, ?int $openVisitRoId): ?Appointment
    {
        if ($customer === null || $openVisitRoId !== null) {
            return null;
        }

        return Appointment::query()
            ->where('customer_id', $customer->id)
            ->whereNull('repair_order_id')
            ->whereIn('status', [
                AppointmentStatus::Scheduled->value,
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Arrived->value,
            ])
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
