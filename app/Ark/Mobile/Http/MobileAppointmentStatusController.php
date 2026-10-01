<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileAppointmentRowProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\ApplyAppointmentStatus;
use App\Ark\Operations\OperationsFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MobileAppointmentStatusController
{
    public function __invoke(
        Request $request,
        Appointment $appointment,
        MobileStaffAccess $access,
        MobileAppointmentRowProjection $rows,
        ApplyAppointmentStatus $applyStatus,
    ): JsonResponse {
        abort_unless($access->canManageAppointments($request->user()), 403);
        abort_unless(OperationsFeatures::appointmentsEnabled(), 404);

        $data = $request->validate([
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
        ]);

        $status = AppointmentStatus::from($data['status']);
        $applyStatus->execute($appointment, $status);

        $appointment->load(['customer', 'vehicle', 'advisor', 'repairOrder']);

        return response()->json([
            'appointment' => $rows->present($appointment->fresh(), $request->user()),
            'message' => 'Appointment marked '.$status->label().'.',
        ]);
    }
}
