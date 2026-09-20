<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\Appointments\AppointmentRequestAvailability;
use App\Ark\Operations\Appointments\AppointmentRequestAvailabilityProjection;

/**
 * Appointment-request projection helpers for the public /book surface.
 * Still creates a website Lead — does not write Appointment Truth.
 */
final class PublicAppointmentRequest
{
    public const SURFACE_PAGE = 'book';

    /**
     * Legacy preference strings (older leads). New submits use preferred_date + preferred_period.
     *
     * @var list<string>
     */
    public const AVAILABILITY_OPTIONS = [
        'As soon as possible',
        'Within a few days',
        'Later this week',
        'Next week',
        'Flexible — call or text me to schedule',
    ];

    public static function isBookSurface(?array $metadata): bool
    {
        $page = data_get($metadata, 'public_surface.page');

        if (is_string($page) && $page === self::SURFACE_PAGE) {
            return true;
        }

        // Appointment-request metadata may arrive from Common Problems Book form, etc.
        return filled(data_get($metadata, 'appointment_request.preferred_date'))
            || filled(data_get($metadata, 'appointment_request.preferred_availability'));
    }

    public static function isBookSurfaceFromLead(\App\Ark\Operations\Leads\Lead $lead): bool
    {
        return self::isBookSurface(is_array($lead->metadata) ? $lead->metadata : null);
    }

    /**
     * Fold preferred visit window into the concern so intake sees it without new authority.
     */
    public static function composeConcern(string $concern, string $preferredLabel): string
    {
        $concern = trim($concern);
        $preferredLabel = trim($preferredLabel);

        if ($preferredLabel === '') {
            return $concern;
        }

        return "Preferred visit: {$preferredLabel}\n\n{$concern}";
    }

    public static function preferredLabel(string $dateLabel, string $period): string
    {
        $periodLabel = AppointmentRequestAvailability::periodLabel($period);

        if ($period === AppointmentRequestAvailability::PERIOD_ANY) {
            return $dateLabel;
        }

        return "{$dateLabel} · {$periodLabel}";
    }

    /**
     * @param  array<string, mixed>|null  $surfaceMetadata
     * @return array<string, mixed>
     */
    public static function metadataWithAvailability(
        ?array $surfaceMetadata,
        string $preferredDate,
        string $preferredPeriod,
        string $preferredLabel,
    ): array {
        $metadata = $surfaceMetadata ?? [];

        $metadata['appointment_request'] = [
            'preferred_date' => $preferredDate,
            'preferred_period' => $preferredPeriod,
            'preferred_label' => $preferredLabel,
            // Keep legacy key for intake/thanks readers that still look for a single string.
            'preferred_availability' => $preferredLabel,
        ];

        return $metadata;
    }

    /**
     * @return array{
     *     accepting_requests: bool,
     *     horizon_days: int,
     *     minimum_notice_days: int,
     *     dates: list<array{date: string, label: string, weekday: string}>,
     *     periods: list<array{value: string, label: string}>,
     *     request_windows: array{
     *         morning: array{enabled: bool, open: string, close: string},
     *         afternoon: array{enabled: bool, open: string, close: string},
     *         flexible_enabled: bool,
     *         latest_appointment_arrival: string|null
     *     },
     *     empty_message: string|null
     * }
     */
    public static function availabilityProjection(): array
    {
        return app(AppointmentRequestAvailabilityProjection::class)->forBook();
    }
}
