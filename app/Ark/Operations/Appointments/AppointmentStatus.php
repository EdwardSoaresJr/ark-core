<?php

namespace App\Ark\Operations\Appointments;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Arrived = 'arrived';
    case NoShow = 'no_show';
    case Completed = 'completed';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Confirmed => 'Confirmed',
            self::Arrived => 'Checked in',
            self::NoShow => 'No show',
            self::Completed => 'Completed',
            self::Canceled => 'Canceled',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Scheduled, self::Confirmed, self::Arrived], true);
    }

    /** Booked on the calendar, vehicle not yet in for this slot. */
    public function isUpcoming(): bool
    {
        return in_array($this, [self::Scheduled, self::Confirmed], true);
    }

    /**
     * @return list<self>
     */
    public static function activeToday(): array
    {
        return [self::Scheduled, self::Confirmed, self::Arrived];
    }
}
