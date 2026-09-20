<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Intake\IntakeEntryQuery;
use App\Ark\Operations\Leads\IngressCreateContactUrl;
use Illuminate\View\View;

class AppointmentShowController
{
    public function __invoke(Appointment $appointment, AppointmentStaffOptions $staff, AppointmentSmsDelivery $smsDelivery): View
    {
        $appointment->load(['customer.vehicles', 'vehicle', 'advisor', 'technician', 'workstation', 'repairOrder', 'creator', 'lead']);

        $smsCanSend = $smsDelivery->canSend($appointment);
        $smsBlockReason = $smsDelivery->blockReason($appointment);

        $createCustomerUrl = null;
        if ($appointment->customer_id === null) {
            if ($appointment->lead !== null) {
                $createCustomerUrl = IngressCreateContactUrl::forLead($appointment->lead);
            } elseif (filled($appointment->contact_phone)) {
                $createCustomerUrl = IngressCreateContactUrl::forPhone((string) $appointment->contact_phone);
            }
        }

        return view('operations.appointments.show', [
            'appointment' => $appointment,
            'advisors' => $staff->advisors(),
            'technicians' => $staff->techniciansForAppointmentSelect($appointment->technician),
            'workstations' => $staff->workstationsForAppointmentSelect($appointment->workstation),
            'statuses' => AppointmentStatus::cases(),
            'openEditor' => request()->boolean('edit'),
            'slotMinutes' => AppointmentSlotMinutes::resolve(),
            'openCommsPrompt' => session('appointment_comms_prompt') || request()->boolean('comms'),
            'smsCanSend' => $smsCanSend,
            'smsBlockReason' => $smsBlockReason,
            'reminderHoursOptions' => AppointmentReminderSettingsController::HOURS_OPTIONS,
            'confirmationPreview' => $smsCanSend || filled($appointment->displayPhone())
                ? AppointmentSmsCopy::confirmation($appointment)
                : null,
            'intakeUrl' => route('operations.intake.create', IntakeEntryQuery::fromAppointment($appointment)),
            'createCustomerUrl' => $createCustomerUrl,
        ]);
    }
}
