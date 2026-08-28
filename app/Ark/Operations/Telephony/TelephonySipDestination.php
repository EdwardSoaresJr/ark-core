<?php

namespace App\Ark\Operations\Telephony;

final class TelephonySipDestination
{
    public static function normalize(string $destination): string
    {
        $destination = trim($destination);

        if ($destination === '') {
            return '';
        }

        if (! str_starts_with(strtolower($destination), 'sip:')) {
            $destination = 'sip:'.$destination;
        }

        return $destination;
    }
}
