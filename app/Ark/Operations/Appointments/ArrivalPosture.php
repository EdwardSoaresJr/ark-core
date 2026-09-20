<?php

namespace App\Ark\Operations\Appointments;

/**
 * Disposable read-model payload for Arrival Posture on an RO.
 * Rebuildable from Appointment authority — owns no persistence.
 */
final class ArrivalPosture
{
    /**
     * @param  'scheduled'|'arrived'|'completed'|'no_show'|'canceled'|null  $posture
     */
    public function __construct(
        public readonly bool $present,
        public readonly ?string $posture,
        public readonly ?string $headline,
        public readonly ?string $whenLabel,
        public readonly ?string $subtitle,
        public readonly ?string $appointmentUrl,
        public readonly ?string $scheduleUrl,
        public readonly ?AppointmentStatus $sourceStatus,
        public readonly ?int $appointmentId = null,
    ) {}

    public static function absent(?string $scheduleUrl = null): self
    {
        return new self(
            present: false,
            posture: null,
            headline: null,
            whenLabel: null,
            subtitle: null,
            appointmentUrl: null,
            scheduleUrl: $scheduleUrl,
            sourceStatus: null,
            appointmentId: null,
        );
    }
}
