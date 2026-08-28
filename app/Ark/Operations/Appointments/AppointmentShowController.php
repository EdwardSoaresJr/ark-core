<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\View\View;

class AppointmentShowController
{
    public function __invoke(Appointment $appointment, AppointmentStaffOptions $staff): View
    {
        $appointment->load(['customer.vehicles', 'vehicle', 'advisor', 'technician', 'workstation', 'repairOrder', 'creator']);

        $smsEligibility = $appointment->customer
            ? CustomerSmsSendEligibility::for(
                $appointment->customer,
                app(ShopIntegrationCredentials::class),
            )
            : null;

        return view('operations.appointments.show', [
            'appointment' => $appointment,
            'advisors' => $staff->advisors(),
            'technicians' => $staff->techniciansForAppointmentSelect($appointment->technician),
            'workstations' => $staff->workstationsForAppointmentSelect($appointment->workstation),
            'statuses' => AppointmentStatus::cases(),
            'openEditor' => request()->boolean('edit'),
            'slotMinutes' => AppointmentSlotMinutes::resolve(),
            'openCommsPrompt' => session('appointment_comms_prompt') || request()->boolean('comms'),
            'smsCanSend' => $smsEligibility?->canSend() ?? false,
            'smsBlockReason' => $smsEligibility?->blockReason(),
            'reminderHoursOptions' => AppointmentReminderSettingsController::HOURS_OPTIONS,
            'confirmationPreview' => $appointment->customer
                ? AppointmentSmsCopy::confirmation($appointment)
                : null,
        ]);
    }
}
