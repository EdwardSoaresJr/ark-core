<?php

namespace App\Ark\Operations\Conversations\Projections;

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Timeline\OperationalEventTone;
use Illuminate\Support\Carbon;

use App\Models\User;

/**
 * Presents one authority-backed timeline row as a customer/shop activity.
 */
final class ConversationActivityPresenter
{
    public function __construct(
        private readonly ConversationActivityActionBuilder $actionBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(
        OperationalEventEntry $entry,
        ConversationSurface $surface,
        ?\App\Ark\Operations\RepairOrders\RepairOrder $primaryRepairOrder = null,
        ?string $contactPhone = null,
        ?array $recordingMetadata = null,
        ?User $viewer = null,
    ): array {
        $metadata = $entry->metadata;

        if ($recordingMetadata !== null) {
            $metadata = [...$metadata, ...$recordingMetadata];
        }

        $occurredAt = $entry->occurredAt->copy()->timezone($this->displayTimezone());

        return [
            'id' => $this->stableId($entry),
            'source' => $entry->source->value,
            'kind' => $entry->kind->value,
            'activity_type' => $this->activityType($entry),
            'activity_side' => $this->activitySide($entry),
            'activity_label' => $this->activityLabel($entry),
            'category_label' => $this->categoryLabel($entry),
            'headline' => $entry->headline,
            'summary' => $entry->body,
            'body' => $entry->body,
            'actor' => $entry->actor,
            'tone' => $entry->tone->value,
            'occurred_at' => $entry->occurredAt->toIso8601String(),
            'day_label' => $this->dayLabel($occurredAt),
            'time_label' => $occurredAt->format('g:i A'),
            'metadata' => $metadata,
            'actions' => $this->actionBuilder->forEntry($entry, $surface, $primaryRepairOrder, $contactPhone, $viewer),
        ];
    }

    private function stableId(OperationalEventEntry $entry): string
    {
        $metadata = $entry->metadata;

        return match ($entry->source) {
            OperationalEventSource::CallSession => 'call_session:'.($metadata['call_session_id'] ?? 'unknown'),
            OperationalEventSource::CommunicationEvent => 'communication_event:'.($metadata['communication_event_id'] ?? $entry->occurredAt->timestamp),
            OperationalEventSource::OperationalEvent => 'operational_event:'.($metadata['operational_event_id'] ?? $entry->occurredAt->timestamp),
            OperationalEventSource::Approval => 'approval:'.($metadata['approval_event_id'] ?? $entry->occurredAt->timestamp),
            default => $entry->source->value.':'.$entry->kind->value.':'.$entry->occurredAt->timestamp,
        };
    }

    private function activitySide(OperationalEventEntry $entry): string
    {
        return match ($entry->tone) {
            OperationalEventTone::Customer => 'customer',
            OperationalEventTone::Internal => 'internal',
            default => 'shop',
        };
    }

    private function activityType(OperationalEventEntry $entry): string
    {
        if ($entry->kind === OperationalEventKind::Call
            && ($entry->metadata['has_recording'] ?? false) === true
            && ($entry->metadata['has_voicemail'] ?? false) !== true
            && ($entry->metadata['is_missed'] ?? false) !== true) {
            return 'recording';
        }

        if ($entry->kind === OperationalEventKind::AiSummary) {
            return 'advisor_brief';
        }

        return match ($entry->kind) {
            OperationalEventKind::Sms => $entry->tone === OperationalEventTone::Customer
                ? 'inbound_sms'
                : 'outbound_sms',
            OperationalEventKind::Call => $entry->tone === OperationalEventTone::Customer
                ? 'inbound_call'
                : 'outbound_call',
            OperationalEventKind::MissedCall => 'missed_call',
            OperationalEventKind::Voicemail => 'voicemail',
            OperationalEventKind::Recording => 'recording',
            OperationalEventKind::InternalNote => 'internal_note',
            OperationalEventKind::EstimateSent => 'estimate_sent',
            OperationalEventKind::EstimateViewed => 'estimate_viewed',
            OperationalEventKind::Portal,
            OperationalEventKind::Logged => 'portal_reply',
            default => $entry->kind->value,
        };
    }

    private function activityLabel(OperationalEventEntry $entry): string
    {
        return match ($entry->kind) {
            OperationalEventKind::Sms => $entry->tone === OperationalEventTone::Customer
                ? 'Customer texted'
                : 'Shop texted',
            OperationalEventKind::Email => $entry->tone === OperationalEventTone::Customer
                ? 'Customer emailed'
                : 'Shop emailed',
            OperationalEventKind::Messenger => 'Messenger message',
            OperationalEventKind::InternalNote => 'Internal note',
            OperationalEventKind::Call => 'Phone call',
            OperationalEventKind::MissedCall => 'Missed call',
            OperationalEventKind::Voicemail => 'Voicemail',
            OperationalEventKind::EstimateViewed => 'Estimate viewed',
            OperationalEventKind::EstimateSent => 'Estimate sent',
            OperationalEventKind::Approval => 'Work approved',
            OperationalEventKind::Payment => 'Payment',
            OperationalEventKind::PortalActivity => 'Portal activity',
            OperationalEventKind::Portal => 'Portal message',
            OperationalEventKind::Inspection => 'Inspection update',
            OperationalEventKind::VehicleStatus => 'Vehicle status',
            OperationalEventKind::Appointment => 'Appointment',
            default => $entry->headline,
        };
    }

    private function categoryLabel(OperationalEventEntry $entry): string
    {
        return match ($entry->kind) {
            OperationalEventKind::Sms,
            OperationalEventKind::Email,
            OperationalEventKind::Messenger,
            OperationalEventKind::Portal,
            OperationalEventKind::InternalNote => 'Message',
            OperationalEventKind::Call,
            OperationalEventKind::MissedCall,
            OperationalEventKind::Voicemail => 'Call',
            OperationalEventKind::EstimateViewed,
            OperationalEventKind::EstimateSent => 'Estimate',
            OperationalEventKind::Approval => 'Approval',
            OperationalEventKind::Payment => 'Payment',
            OperationalEventKind::PortalActivity => 'Portal',
            OperationalEventKind::Inspection => 'Inspection',
            OperationalEventKind::VehicleStatus => 'Vehicle',
            OperationalEventKind::Appointment => 'Appointment',
            default => 'Activity',
        };
    }

    private function dayLabel(Carbon $occurredAt): string
    {
        $today = now($this->displayTimezone())->startOfDay();

        if ($occurredAt->isSameDay($today)) {
            return 'Today';
        }

        if ($occurredAt->isSameDay($today->copy()->subDay())) {
            return 'Yesterday';
        }

        return $occurredAt->format('M j, Y');
    }

    private function displayTimezone(): string
    {
        return (string) config('app.display_timezone', config('app.timezone'));
    }
}
