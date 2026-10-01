<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Mobile\MobileAppointmentRowProjection;
use App\Ark\Mobile\MobileStaffAccess;
use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\ValidatesAppointments;
use App\Ark\Operations\OperationsFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileAppointmentStoreController
{
    use ValidatesAppointments;

    public function __invoke(
        Request $request,
        MobileStaffAccess $access,
        MobileAppointmentRowProjection $rows,
    ): JsonResponse {
        abort_unless($access->canPerformIntake($request->user()), 403);
        abort_unless(OperationsFeatures::appointmentsEnabled(), 404);

        [$data] = $this->normalizeAppointmentInput(
            $this->validatedAppointment($request),
            $request,
        );

        if (! isset($data['advisor_user_id'])) {
            $data['advisor_user_id'] = $request->user()->id;
        }

        $appointment = Appointment::query()->create([
            ...$data,
            'created_by_user_id' => $request->user()->id,
            'status' => $data['status'] ?? AppointmentStatus::Scheduled,
        ]);

        $appointment->load(['customer', 'vehicle', 'advisor', 'repairOrder']);

        return response()->json([
            'appointment' => $rows->present($appointment, $request->user()),
            'message' => 'Appointment scheduled.',
        ], 201);
    }
}
