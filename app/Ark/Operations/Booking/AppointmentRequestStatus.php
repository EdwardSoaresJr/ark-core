<?php

namespace App\Ark\Operations\Booking;

enum AppointmentRequestStatus: string
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for the shop',
            self::Scheduled => 'Scheduled',
            self::Declined => 'Declined',
        };
    }
}
