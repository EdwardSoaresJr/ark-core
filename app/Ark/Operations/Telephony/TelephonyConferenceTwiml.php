<?php

namespace App\Ark\Operations\Telephony;

final class TelephonyConferenceTwiml
{
    public static function customerWaitConferenceXml(string $conferenceName): string
    {
        return self::conferenceXml(
            $conferenceName,
            startConferenceOnEnter: true,
            includeWaitUrl: false,
        );
    }

    public static function staffJoinResponse(string $conferenceName): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Response><Dial>'
            .self::conferenceXml(
                $conferenceName,
                startConferenceOnEnter: true,
                includeWaitUrl: false,
            )
            .'</Dial></Response>';
    }

    private static function conferenceXml(
        string $conferenceName,
        bool $startConferenceOnEnter,
        bool $includeWaitUrl,
    ): string {
        $waitUrl = $includeWaitUrl
            ? ' waitUrl="'.htmlspecialchars(route('webhooks.communications.twilio.voice.conference-wait'), ENT_XML1).'"'
            : '';
        $start = $startConferenceOnEnter ? 'true' : 'false';

        return '<Conference beep="false" startConferenceOnEnter="'.$start.'" endConferenceOnExit="true"'
            .$waitUrl.'>'
            .htmlspecialchars($conferenceName, ENT_XML1)
            .'</Conference>';
    }
}
