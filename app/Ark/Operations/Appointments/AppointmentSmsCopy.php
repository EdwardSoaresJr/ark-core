<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Str;

/**
 * Customer-facing appointment SMS copy — ConversationMessage body only.
 * Reply menus are Message Action contracts (see MessageActionContract).
 */
final class AppointmentSmsCopy
{
    public static function confirmation(Appointment $appointment): string
    {
        $shop = self::shopName();
        $when = AppointmentExpectationFormatter::confirmedWhenLabel($appointment);
        $concern = self::concernSnippet($appointment);

        return self::withReplyMenu(
            "{$shop}: You're scheduled {$when} for {$concern}.",
        );
    }

    public static function dayBeforeReminder(Appointment $appointment): string
    {
        $shop = self::shopName();
        $time = AppointmentExpectationFormatter::confirmedTimeFragment($appointment);
        $concern = self::concernSnippet($appointment);

        return self::withReplyMenu(
            "{$shop} reminder: Appointment tomorrow at {$time} for {$concern}.",
        );
    }

    public static function hoursBeforeReminder(Appointment $appointment, int $hours): string
    {
        $shop = self::shopName();
        $time = AppointmentExpectationFormatter::confirmedTimeFragment($appointment);
        $concern = self::concernSnippet($appointment);
        $window = $hours === 1 ? 'about 1 hour' : "about {$hours} hours";

        return self::withReplyMenu(
            "{$shop} reminder: Your appointment is in {$window} ({$time}) for {$concern}.",
        );
    }

    private static function withReplyMenu(string $lead): string
    {
        return trim($lead)."\n\n"
            ."Reply:\n"
            ."1 - Confirm\n"
            ."2 - Reschedule\n"
            ."3 - Get Directions\n"
            ."4 - Call Me\n\n"
            .'Reply STOP to opt out.';
    }

    private static function concernSnippet(Appointment $appointment): string
    {
        $concern = trim((string) $appointment->concern);

        if ($concern === '') {
            return 'your visit';
        }

        return Str::limit($concern, 80, '…');
    }

    private static function shopName(): string
    {
        $name = trim((string) (ShopSettings::current()->shop_name ?? ''));

        return $name !== '' ? $name : 'Your shop';
    }
}
