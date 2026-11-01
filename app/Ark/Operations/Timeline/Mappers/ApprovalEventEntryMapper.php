<?php

namespace App\Ark\Operations\Timeline\Mappers;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalEventStaffPresentation;
use App\Ark\Operations\Approvals\ApprovalRevocationEvent;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Timeline\OperationalEventTone;

final class ApprovalEventEntryMapper
{
    /**
     * @return list<OperationalEventEntry>
     */
    public function map(ApprovalEvent $approval): array
    {
        $approval->loadMissing(['visit.customer', 'visit.vehicle', 'revocation']);

        $entries = [
            new OperationalEventEntry(
                source: OperationalEventSource::Approval,
                kind: OperationalEventKind::Approval,
                occurredAt: $approval->approved_at ?? now(),
                headline: ApprovalEventStaffPresentation::isDeferredResponse($approval)
                    ? 'Customer deferred recommended work'
                    : 'Customer authorized '.$approval->approval_type->label(),
                body: $approval->source->label().' approval · '.(ApprovalEventStaffPresentation::recordedAmountCents($approval) > 0
                    ? $this->money(ApprovalEventStaffPresentation::recordedAmountCents($approval))
                    : mb_strtolower(ApprovalEventStaffPresentation::amountLabel($approval))),
                actor: $approval->approved_by ?: 'Customer',
                tone: OperationalEventTone::Success,
                links: [],
                metadata: [
                    'hub_filter' => 'portal',
                    'timeline_category' => 'approval',
                    'approval_event_id' => $approval->id,
                    'repair_order_id' => $approval->visit?->repair_order_id,
                ],
                subject: $approval,
            ),
        ];

        if ($approval->revocation instanceof ApprovalRevocationEvent) {
            $entries[] = new OperationalEventEntry(
                source: OperationalEventSource::Approval,
                kind: OperationalEventKind::Approval,
                occurredAt: $approval->revocation->revoked_at ?? now(),
                headline: 'Authorization revoked',
                body: $approval->revocation->source->label().' revocation recorded',
                actor: $approval->revocation->revoked_by,
                tone: OperationalEventTone::Warning,
                links: [],
                metadata: [
                    'hub_filter' => 'portal',
                    'timeline_category' => 'approval',
                    'approval_event_id' => $approval->id,
                    'repair_order_id' => $approval->visit?->repair_order_id,
                    'revoked' => true,
                ],
                subject: $approval,
            );
        }

        return $entries;
    }

    private function money(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }
}
