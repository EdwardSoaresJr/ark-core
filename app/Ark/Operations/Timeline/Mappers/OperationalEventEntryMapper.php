<?php

namespace App\Ark\Operations\Timeline\Mappers;

use App\Ark\Operations\Events\EventContract;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Portal\RepairOrderPortalActivity;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Timeline\OperationalEventTone;

final class OperationalEventEntryMapper
{
    /** @var list<string> */
    public const CUSTOMER_TIMELINE_NAMES = [
        OperationalEventName::RepairOrderLifecycleChanged->value,
        OperationalEventName::RepairOrderPaymentReceived->value,
        OperationalEventName::RepairOrderCreated->value,
        OperationalEventName::EstimateDocumentGenerated->value,
        OperationalEventName::EstimateDocumentRefreshed->value,
        OperationalEventName::EstimateEmailedToCustomer->value,
        OperationalEventName::InvoiceEmailedToCustomer->value,
        OperationalEventName::ConcernDispositionChanged->value,
        OperationalEventName::ConcernProductionStatusChanged->value,
        OperationalEventName::PortalDocumentViewed->value,
        OperationalEventName::PortalDocumentDownloaded->value,
        OperationalEventName::PortalActiveVisitViewed->value,
        OperationalEventName::PortalVehicleViewed->value,
        OperationalEventName::PortalCommunicationSectionViewed->value,
    ];

    public function __construct(
        private readonly RepairOrderPortalActivity $portalActivity,
    ) {}

    public function map(OperationalEvent $event): ?OperationalEventEntry
    {
        $event->loadMissing('actor:id,name');

        $name = OperationalEventName::tryFrom($event->event_name);

        if ($name === null) {
            return null;
        }

        $payload = $event->payload_json ?? [];

        return match ($name) {
            OperationalEventName::RepairOrderLifecycleChanged => new OperationalEventEntry(
                source: OperationalEventSource::OperationalEvent,
                kind: OperationalEventKind::VehicleStatus,
                occurredAt: $event->occurred_at ?? now(),
                headline: 'Vehicle moved to '.$this->statusLabel($payload['to_status'] ?? null),
                body: 'Previously '.$this->statusLabel($payload['from_status'] ?? null),
                actor: $event->actor?->name,
                tone: OperationalEventTone::Neutral,
                metadata: $this->workflowMetadata($event, 'vehicle_status'),
                subject: $event,
            ),
            OperationalEventName::RepairOrderPaymentReceived => new OperationalEventEntry(
                source: OperationalEventSource::OperationalEvent,
                kind: OperationalEventKind::Payment,
                occurredAt: $event->occurred_at ?? now(),
                headline: EventContract::PaymentReceived->label(),
                body: $this->money((int) ($payload['amount_cents'] ?? 0)).' collected',
                actor: $event->actor?->name,
                tone: OperationalEventTone::Success,
                metadata: $this->workflowMetadata($event, 'payment', EventContract::PaymentReceived),
                subject: $event,
            ),
            OperationalEventName::RepairOrderCreated => new OperationalEventEntry(
                source: OperationalEventSource::OperationalEvent,
                kind: OperationalEventKind::VehicleStatus,
                occurredAt: $event->occurred_at ?? now(),
                headline: 'Repair order opened',
                body: filled($payload['concern_summary'] ?? null) ? (string) $payload['concern_summary'] : null,
                actor: $event->actor?->name,
                tone: OperationalEventTone::Neutral,
                metadata: $this->workflowMetadata($event, 'vehicle_status'),
                subject: $event,
            ),
            OperationalEventName::EstimateDocumentGenerated,
            OperationalEventName::EstimateDocumentRefreshed,
            OperationalEventName::EstimateEmailedToCustomer => new OperationalEventEntry(
                source: OperationalEventSource::OperationalEvent,
                kind: OperationalEventKind::EstimateSent,
                occurredAt: $event->occurred_at ?? now(),
                headline: $this->estimateHeadline($name),
                body: filled($payload['document_number'] ?? null)
                    ? 'Document '.$payload['document_number']
                    : null,
                actor: $event->actor?->name,
                tone: OperationalEventTone::Shop,
                metadata: $this->workflowMetadata($event, 'estimate'),
                subject: $event,
            ),
            OperationalEventName::InvoiceEmailedToCustomer => new OperationalEventEntry(
                source: OperationalEventSource::OperationalEvent,
                kind: OperationalEventKind::EstimateSent,
                occurredAt: $event->occurred_at ?? now(),
                headline: 'Invoice emailed to customer',
                body: filled($payload['document_number'] ?? null)
                    ? 'Document '.$payload['document_number']
                    : null,
                actor: $event->actor?->name,
                tone: OperationalEventTone::Shop,
                metadata: $this->workflowMetadata($event, 'payment'),
                subject: $event,
            ),
            OperationalEventName::ConcernDispositionChanged => new OperationalEventEntry(
                source: OperationalEventSource::OperationalEvent,
                kind: OperationalEventKind::Approval,
                occurredAt: $event->occurred_at ?? now(),
                headline: $this->dispositionHeadline($payload),
                body: 'Previously '.$this->dispositionLabel($payload['prior_disposition'] ?? null),
                actor: $event->actor?->name,
                tone: OperationalEventTone::Customer,
                metadata: $this->workflowMetadata($event, 'approval'),
                subject: $event,
            ),
            OperationalEventName::ConcernProductionStatusChanged => new OperationalEventEntry(
                source: OperationalEventSource::OperationalEvent,
                kind: OperationalEventKind::Inspection,
                occurredAt: $event->occurred_at ?? now(),
                headline: 'Production status updated',
                body: collect([
                    filled($payload['to_status'] ?? null) ? (string) $payload['to_status'] : null,
                    filled($payload['concern_title'] ?? null) ? (string) $payload['concern_title'] : null,
                ])->filter()->join(' · '),
                actor: $event->actor?->name,
                tone: OperationalEventTone::Neutral,
                metadata: $this->workflowMetadata($event, 'inspection'),
                subject: $event,
            ),
            OperationalEventName::PortalDocumentViewed,
            OperationalEventName::PortalDocumentDownloaded,
            OperationalEventName::PortalActiveVisitViewed,
            OperationalEventName::PortalVehicleViewed,
            OperationalEventName::PortalCommunicationSectionViewed => new OperationalEventEntry(
                source: OperationalEventSource::OperationalEvent,
                kind: OperationalEventKind::PortalActivity,
                occurredAt: $event->occurred_at ?? now(),
                headline: $this->portalActivity->label($event),
                body: $this->portalActivity->summary($event),
                actor: 'Customer',
                tone: OperationalEventTone::Customer,
                metadata: $this->workflowMetadata($event, 'portal'),
                subject: $event,
            ),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowMetadata(OperationalEvent $event, string $category, ?EventContract $contract = null): array
    {
        $payload = $event->payload_json ?? [];

        return [
            'hub_filter' => match ($category) {
                'portal' => 'portal',
                'payment' => 'portal',
                default => 'logged',
            },
            'timeline_category' => $category,
            'operational_event_id' => $event->id,
            'event_name' => $event->event_name,
            'event_contract' => $contract?->value ?? ($payload['event_contract'] ?? null),
            'repair_order_id' => $payload['repair_order_id'] ?? null,
        ];
    }

    private function estimateHeadline(OperationalEventName $name): string
    {
        return match ($name) {
            OperationalEventName::EstimateDocumentGenerated => 'Estimate document generated',
            OperationalEventName::EstimateDocumentRefreshed => 'Estimate document refreshed',
            OperationalEventName::EstimateEmailedToCustomer => 'Estimate emailed to customer',
            default => 'Estimate activity',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispositionHeadline(array $payload): string
    {
        $label = $this->dispositionLabel($payload['disposition'] ?? null);

        return $label !== 'Unknown' ? 'Concern '.$label : 'Concern disposition changed';
    }

    private function dispositionLabel(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return 'Unknown';
        }

        return str($value)->replace('_', ' ')->title()->toString();
    }

    private function statusLabel(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return 'Unknown';
        }

        return app(RepairOrderStatusCatalog::class)->labelForSlug($value);
    }

    private function money(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }
}
