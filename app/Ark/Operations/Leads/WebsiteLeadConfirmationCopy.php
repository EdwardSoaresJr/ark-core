<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Leads\Public\PublicAppointmentRequest;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Telephony\TelephonyBusinessHoursLabel;
use App\Support\Mail\ShopMailBranding;

final class WebsiteLeadConfirmationCopy
{
    public static function smsBody(Lead $lead): string
    {
        $shopName = ShopMailBranding::shopName();
        $responseHint = self::responseTimeHint();
        $firstName = self::firstName($lead);
        $appointmentRequest = PublicAppointmentRequest::isBookSurfaceFromLead($lead);

        $greeting = filled($firstName) ? "Hi {$firstName}, " : '';

        $body = $appointmentRequest
            ? sprintf(
                '%s%s: We received your appointment request. We’ll confirm the appointment time with you soon.',
                $greeting,
                $shopName,
            )
            : sprintf(
                '%s%s: We received your request. A service advisor will review it and follow up soon.',
                $greeting,
                $shopName,
            );

        if (filled($responseHint)) {
            $body .= ' '.$responseHint;
        }

        $body .= ' Reply STOP to opt out.';

        return $body;
    }

    public static function emailSubject(?Lead $lead = null): string
    {
        $shopName = ShopMailBranding::shopName();

        if ($lead instanceof Lead && PublicAppointmentRequest::isBookSurfaceFromLead($lead)) {
            return sprintf('%s — appointment request received', $shopName);
        }

        return sprintf('%s — request received', $shopName);
    }

    /**
     * @return array{intro: string, response_hint: string|null, phone_display: string|null}
     */
    public static function emailViewData(Lead $lead): array
    {
        $shop = ShopSettings::current();
        $appointmentRequest = PublicAppointmentRequest::isBookSurfaceFromLead($lead);

        return [
            'intro' => $appointmentRequest
                ? sprintf(
                    '%s we received your appointment request and will confirm the appointment time with you soon.',
                    filled(self::firstName($lead)) ? self::firstName($lead).',' : 'Hi,',
                )
                : sprintf(
                    '%s we received your vehicle concern and a service advisor will review it soon.',
                    filled(self::firstName($lead)) ? self::firstName($lead).',' : 'Hi,',
                ),
            'response_hint' => self::responseTimeHint(),
            'phone_display' => PhoneNumber::display($shop->phone),
        ];
    }

    private static function responseTimeHint(): ?string
    {
        $hours = TelephonyBusinessHoursLabel::fromCallFlow();

        if ($hours === '' || $hours === 'Closed') {
            return null;
        }

        return 'Shop hours: '.$hours.'.';
    }

    private static function firstName(Lead $lead): ?string
    {
        $name = trim((string) ($lead->contact_name ?? ''));

        if ($name === '') {
            return null;
        }

        return strtok($name, ' ') ?: null;
    }
}
