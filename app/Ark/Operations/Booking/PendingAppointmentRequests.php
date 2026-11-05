<?php

namespace App\Ark\Operations\Booking;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Carbon\CarbonInterface;

/**
 * The Schedule's queue of online requests waiting for staff, soonest preferred day first.
 * Each row links into the normal appointment form with the customer's preference; staff choose the time.
 */
final class PendingAppointmentRequests
{
    public const LIMIT = 50;

    /**
     * @return array{count: int, for_day: int, rows: list<array<string, mixed>>}
     */
    public function forSchedule(?CarbonInterface $focusDay = null): array
    {
        $focus = $focusDay?->toDateString();
        $requests = AppointmentRequest::query()
            ->with('lead')
            ->where('status', AppointmentRequestStatus::Pending->value)
            ->orderByRaw('preferred_date is null')
            ->orderBy('preferred_date')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();

        $rows = $requests->map(fn (AppointmentRequest $request): array => [
            'id' => $request->id,
            'name' => $request->contact_name,
            'phone' => $request->contact_phone,
            'service' => $request->serviceLabel(),
            'preference' => $request->preferenceLabel(),
            'concern' => $request->concern,
            'vehicles' => $request->vehicleLabels(),
            'location' => $request->locationType()->isOnSite() ? $request->locationType()->label() : null,
            'address' => $request->locationType()->isOnSite() ? $request->addressLabel() : null,
            'answers' => is_array($request->answers) ? $request->answers : [],
            'received' => ShopDisplayTimezone::format($request->created_at, 'M j, g:i A'),
            'for_focus_day' => $focus !== null && $request->preferred_date?->toDateString() === $focus,
            'past_due' => $request->preferred_date !== null && $request->preferred_date->toDateString() < ShopDisplayTimezone::now()->toDateString(),
            'schedule_url' => route('operations.schedule', array_filter([
                'lead' => $request->lead_id,
                'appointment_request' => $request->id,
                'preferred_date' => $request->preferred_date?->toDateString(),
                'preferred_period' => $request->preferred_period,
                'duration_minutes' => $request->duration_minutes,
            ])),
        ])->values()->all();

        return [
            'count' => AppointmentRequest::query()->where('status', AppointmentRequestStatus::Pending->value)->count(),
            'for_day' => count(array_filter($rows, fn (array $row): bool => $row['for_focus_day'])),
            'rows' => $rows,
        ];
    }
}
