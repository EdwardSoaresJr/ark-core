<?php

namespace App\Ark\Operations\Leads\Measurement;

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadState;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Arms one lead conversion after a verified appointment request is stored,
 * then lets the confirmation page emit it a single time.
 */
final class AppointmentRequestConversion
{
    public const SESSION_KEY = 'ark.appointment_request_conversion';

    public static function arm(Request $request, Lead $lead): void
    {
        if (! AppointmentRequestMeasurement::enabled() || $lead->state !== LeadState::Received) {
            return;
        }

        $eventId = (string) Str::uuid();
        $metadata = is_array($lead->metadata) ? $lead->metadata : [];
        $metadata['measurement'] = [
            'event' => 'generate_lead',
            'lead_type' => AppointmentRequestMeasurement::LEAD_TYPE,
            'event_id' => $eventId,
            'armed_at' => now()->toIso8601String(),
            'attribution' => RememberAppointmentAttribution::current($request),
        ];
        $lead->forceFill(['metadata' => $metadata])->save();

        $request->session()->put(self::SESSION_KEY, [
            'lead_id' => $lead->id,
            'event_id' => $eventId,
        ]);
    }

    /**
     * @return array{generate_lead: array<string, string>, ads: ?array<string, string>}|null
     */
    public static function claim(Session $session): ?array
    {
        $pending = $session->get(self::SESSION_KEY);
        if (! is_array($pending) || ! AppointmentRequestMeasurement::enabled()) {
            return null;
        }

        $eventId = isset($pending['event_id']) && is_string($pending['event_id']) ? $pending['event_id'] : '';
        $leadId = isset($pending['lead_id']) ? (int) $pending['lead_id'] : 0;
        $lead = $leadId > 0 ? Lead::query()->find($leadId) : null;
        $metadata = $lead !== null && is_array($lead->metadata) ? $lead->metadata : [];
        $measurement = is_array($metadata['measurement'] ?? null) ? $metadata['measurement'] : [];

        $alreadyEmitted = filled($measurement['emitted_at'] ?? null);
        $matches = ($measurement['event_id'] ?? null) === $eventId
            && $eventId !== ''
            && $lead?->state === LeadState::Received;

        if (! $matches || $alreadyEmitted) {
            $session->forget(self::SESSION_KEY);

            return null;
        }

        $metadata['measurement']['emitted_at'] = now()->toIso8601String();
        $lead->forceFill(['metadata' => $metadata])->save();
        $session->forget(self::SESSION_KEY);

        $attribution = is_array($measurement['attribution'] ?? null) ? $measurement['attribution'] : [];
        $generateLead = [
            'lead_type' => AppointmentRequestMeasurement::LEAD_TYPE,
            'transaction_id' => $eventId,
        ];
        foreach (['gclid', 'gbraid', 'wbraid', 'utm_source', 'utm_medium', 'utm_campaign'] as $key) {
            if (isset($attribution[$key]) && is_string($attribution[$key]) && $attribution[$key] !== '') {
                $generateLead[$key] = $attribution[$key];
            }
        }

        $ads = null;
        $adsId = AppointmentRequestMeasurement::adsId();
        $label = AppointmentRequestMeasurement::adsConversionLabel();
        if ($adsId !== null && $label !== null) {
            $ads = [
                'send_to' => $adsId.'/'.$label,
                'transaction_id' => $eventId,
            ];
        }

        return [
            'generate_lead' => $generateLead,
            'ads' => $ads,
        ];
    }
}
