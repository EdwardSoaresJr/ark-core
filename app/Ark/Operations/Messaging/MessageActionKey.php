<?php

namespace App\Ark\Operations\Messaging;

/**
 * Operator-facing Message Action intents — not canned-SMS template keys.
 */
enum MessageActionKey: string
{
    case AppointmentReminder = 'appointment_reminder';
    case AppointmentConfirmation = 'appointment_confirmation';
    case Address = 'address';
    case Pickup = 'pickup';
    case Hours = 'hours';
    case Tow = 'tow';
    case Wifi = 'wifi';

    public function label(): string
    {
        return match ($this) {
            self::AppointmentReminder => 'Appointment Reminder',
            self::AppointmentConfirmation => 'Appointment Confirmation',
            self::Address => 'Send Address',
            self::Pickup => 'Send Pickup Info',
            self::Hours => 'Send Hours',
            self::Tow => 'Send Tow Info',
            self::Wifi => 'Send Wi-Fi',
        };
    }

    /**
     * @return list<self>
     */
    public static function advisorOneTap(): array
    {
        return [self::Address, self::Pickup, self::Hours, self::Tow, self::Wifi];
    }
}
