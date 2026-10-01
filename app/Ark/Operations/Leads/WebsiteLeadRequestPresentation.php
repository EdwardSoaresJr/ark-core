<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Leads\Public\PublicAppointmentRequest;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use Illuminate\Support\Carbon;

/**
 * Conversation-timeline card for a website submission.
 * Reads the lead record. Does not read the acknowledgement text.
 */
final class WebsiteLeadRequestPresentation
{
    /**
     * @return list<array<string, mixed>>
     */
    public function cardsFor(?int $conversationId, ?string $phone, ?int $leadId = null): array
    {
        $conversationId = $conversationId !== null && $conversationId > 0 ? $conversationId : null;
        $leadId = $leadId !== null && $leadId > 0 ? $leadId : null;
        $phone = PhoneNumber::normalize($phone);

        if ($conversationId === null && $leadId === null && ($phone === null || $phone === '')) {
            return [];
        }

        $leads = Lead::query()
            ->where('source', LeadSource::Website)
            ->where('state', '!=', LeadState::Spam)
            ->where(function ($query) use ($conversationId, $phone, $leadId): void {
                if ($leadId !== null) {
                    $query->orWhere('id', $leadId);
                }
                if ($conversationId !== null) {
                    $query->orWhere('conversation_id', $conversationId);
                }
                if ($phone !== null && $phone !== '') {
                    $query->orWhere('contact_phone', $phone);
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->sortBy([
                fn (Lead $lead): int => $lead->created_at?->getTimestamp() ?? 0,
                fn (Lead $lead): int => (int) $lead->id,
            ])
            ->values();

        return $leads
            ->map(fn (Lead $lead): array => $this->card($lead))
            ->all();
    }

    /**
     * @param  list<mixed>  $events
     * @param  list<array<string, mixed>>  $cards
     * @return list<mixed>
     */
    public function merge(array $events, array $cards): array
    {
        if ($cards === []) {
            return $events;
        }

        $combined = array_merge($events, $cards);
        usort($combined, function (mixed $left, mixed $right): int {
            $compared = $this->occurredStamp($left) <=> $this->occurredStamp($right);

            if ($compared !== 0) {
                return $compared;
            }

            return $this->requestRank($left) <=> $this->requestRank($right);
        });

        return $combined;
    }

    /**
     * @return array<string, mixed>
     */
    public function card(Lead $lead): array
    {
        [$preferredVisit, $concern] = $this->splitConcern($lead);
        $appointment = $preferredVisit !== null || PublicAppointmentRequest::isBookSurfaceFromLead($lead);
        $when = $this->submittedAtLabel($lead->created_at);
        $host = $this->submissionHost($lead);
        $submitted = $host !== null ? 'Submitted from '.$host : 'Submitted';

        $fields = [];
        if ($preferredVisit !== null) {
            $fields[] = ['label' => 'Preferred visit', 'value' => $preferredVisit];
        }

        $vehicle = $lead->roughVehicleLabel();
        if ($vehicle !== null) {
            $fields[] = ['label' => 'Vehicle', 'value' => $vehicle];
        }

        $email = trim((string) $lead->contact_email);
        if ($email !== '') {
            $fields[] = ['label' => 'Email', 'value' => $email];
        }

        $phone = PhoneNumber::display((string) $lead->contact_phone) ?? trim((string) $lead->contact_phone);
        if ($phone !== '') {
            $fields[] = ['label' => 'Phone', 'value' => $phone];
        }

        $preference = $lead->contact_preference;
        if ($preference instanceof LeadContactPreference && $preference !== LeadContactPreference::Text) {
            $fields[] = ['label' => 'Contact', 'value' => $preference->formLabel()];
        }

        return [
            'kind' => 'website_request',
            'direction' => 'inbound',
            'lead_id' => $lead->id,
            'title' => $appointment ? 'Appointment request' : 'Website request',
            'appointment' => $appointment,
            'fields' => $fields,
            'body_label' => $appointment ? 'Concern' : 'Message',
            'body' => $concern,
            'attribution' => $when !== null ? $submitted.' · '.$when : $submitted,
            'occurred_at' => $lead->created_at?->toIso8601String(),
            'occurred_at_label' => $when ?? '',
        ];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function splitConcern(Lead $lead): array
    {
        $metadata = is_array($lead->metadata) ? $lead->metadata : [];
        $preferred = $metadata['appointment_request']['preferred_label'] ?? null;
        if (! is_string($preferred) || trim($preferred) === '') {
            $legacy = $metadata['appointment_request']['preferred_availability'] ?? null;
            $preferred = is_string($legacy) ? $legacy : null;
        }
        $preferred = is_string($preferred) ? trim($preferred) : null;
        if ($preferred === '') {
            $preferred = null;
        }

        $concern = trim((string) $lead->concern);
        if (preg_match('/^Preferred visit:\s*(.+?)(?:\R{2,}|\R|$)/u', $concern, $match) === 1) {
            if ($preferred === null) {
                $preferred = trim($match[1]);
            }
            $concern = trim((string) preg_replace('/^Preferred visit:\s*.+?(?:\R{2,}|\R|$)/u', '', $concern, 1));
        }

        return [$preferred, $concern];
    }

    private function submissionHost(Lead $lead): ?string
    {
        $metadata = is_array($lead->metadata) ? $lead->metadata : [];
        $host = $metadata['canonical_host'] ?? $metadata['public_host'] ?? null;

        if (! is_string($host) || trim($host) === '') {
            $referrer = $lead->ingress_referrer;
            $parsed = is_string($referrer) ? parse_url($referrer, PHP_URL_HOST) : null;
            $host = is_string($parsed) ? $parsed : null;
        }

        if (! is_string($host)) {
            return null;
        }

        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        return $host !== '' ? $host : null;
    }

    private function submittedAtLabel(mixed $instant): ?string
    {
        if (! $instant instanceof Carbon) {
            return null;
        }

        $zone = config('app.display_timezone') ?: config('app.timezone');

        return $instant->timezone(is_string($zone) && $zone !== '' ? $zone : 'UTC')->format('M j, g:i A');
    }

    private function occurredStamp(mixed $event): int
    {
        if ($event instanceof OperationalEventEntry) {
            return $event->occurredAt->getTimestamp();
        }

        if (! is_array($event) || ! filled($event['occurred_at'] ?? null)) {
            return 0;
        }

        try {
            return Carbon::parse((string) $event['occurred_at'])->getTimestamp();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function requestRank(mixed $event): int
    {
        return is_array($event) && ($event['kind'] ?? '') === 'website_request' ? 0 : 1;
    }
}
