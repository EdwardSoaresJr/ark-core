<?php

namespace App\Ark\Operations\Booking;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Booking\Events\AppointmentRequestDecided;
use App\Ark\Operations\Leads\LeadState;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Staff decisions on online requests. Scheduling uses the normal appointment form; this links the
 * new appointment to the request it answers. Declining closes the request and its lead.
 */
final class AppointmentRequestDecisions
{
    /**
     * The pending request a new appointment answers: the one named, or the one for its lead.
     */
    public function pendingFor(?int $requestId, ?int $leadId): ?AppointmentRequest
    {
        if ($requestId !== null && $requestId > 0) {
            return AppointmentRequest::query()->whereKey($requestId)->where('status', AppointmentRequestStatus::Pending->value)->first();
        }

        if ($leadId === null || $leadId < 1) {
            return null;
        }

        return AppointmentRequest::query()
            ->where('lead_id', $leadId)
            ->where('status', AppointmentRequestStatus::Pending->value)
            ->latest('id')
            ->first();
    }

    public function scheduled(Appointment $appointment, ?User $actor, ?int $requestId = null): ?AppointmentRequest
    {
        $request = DB::transaction(function () use ($appointment, $actor, $requestId): ?AppointmentRequest {
            $pending = $this->pendingFor($requestId, $appointment->lead_id);
            $request = $pending === null ? null : AppointmentRequest::query()->whereKey($pending->id)->lockForUpdate()->first();

            if ($request === null || ! $request->isPending()) {
                return null;
            }

            $request->forceFill([
                'status' => AppointmentRequestStatus::Scheduled,
                'appointment_id' => $appointment->id,
                'decided_by_user_id' => $actor?->id,
                'decided_at' => now(),
            ])->save();

            if ($appointment->service_location_type === null && $request->locationType()->isOnSite()) {
                $appointment->forceFill([
                    'service_location_type' => $request->locationType()->value,
                    'service_address' => $request->service_address,
                ])->save();
            }

            $lead = $request->lead;
            if ($lead !== null && $lead->state->isOpen()) {
                $lead->forceFill(['state' => LeadState::Scheduled, 'scheduled_at' => $lead->scheduled_at ?? now()])->save();
            }

            return $request;
        });

        if ($request !== null) {
            AppointmentRequestDecided::dispatch($request->id);
        }

        return $request;
    }

    public function decline(AppointmentRequest $request, User $actor, ?string $reason): bool
    {
        $declined = DB::transaction(function () use ($request, $actor, $reason): bool {
            $locked = AppointmentRequest::query()->whereKey($request->id)->lockForUpdate()->first();
            if ($locked === null || ! $locked->isPending()) {
                return false;
            }

            $reason = filled($reason) ? mb_substr(trim((string) $reason), 0, 255) : null;

            $locked->forceFill([
                'status' => AppointmentRequestStatus::Declined,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
                'decline_reason' => $reason,
            ])->save();

            $lead = $locked->lead;
            if ($lead !== null && $lead->state->isOpen()) {
                $lead->forceFill([
                    'state' => LeadState::Lost,
                    'lost_at' => now(),
                    'lost_reason' => 'Appointment request declined'.($reason !== null ? ': '.$reason : ''),
                ])->save();
            }

            return true;
        });

        if ($declined) {
            AppointmentRequestDecided::dispatch($request->id);
        }

        return $declined;
    }
}
