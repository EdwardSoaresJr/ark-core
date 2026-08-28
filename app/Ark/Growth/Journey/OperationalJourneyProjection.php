<?php

namespace App\Ark\Growth\Journey;

use App\Ark\Growth\Identity\IdentityConfidence;
use App\Ark\Growth\Identity\IdentityConfidenceResolver;
use App\Ark\Growth\Journey\JourneyEvidenceFactory;
use App\Ark\Growth\Journey\JourneyEvidenceSource;
use App\Ark\Growth\Models\GrowthAttribution;
use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\Models\GrowthSession;
use App\Ark\Growth\Sessions\GrowthTouchpointType;
use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Telephony\CallSession;
use Illuminate\Support\Carbon;

final class OperationalJourneyProjection
{
    /**
     * Explainable narrative for a repair order — ARK's answer to "why does this RO exist?"
     *
     * Product concept: Operational Journey · Implementation: this projection · Evidence: JourneyEvidenceItem[]
     */
    public function __construct(
        private readonly IdentityConfidenceResolver $identityConfidence,
        private readonly JourneyStoryComposer $storyComposer,
    ) {}

    public function forRepairOrder(RepairOrder $repairOrder): OperationalJourneyProjectionResult
    {
        $session = $this->resolveSession($repairOrder);
        $confidence = $this->identityConfidence->resolveAndPersist($session, $repairOrder);

        if ($session === null) {
            return new OperationalJourneyProjectionResult(
                session: null,
                identityConfidence: $confidence,
                milestones: [],
                summaryCards: [],
                pathLabels: [],
                durationDays: null,
                hasStory: false,
            );
        }

        $entries = array_merge(
            $this->growthSessionEntries($session),
            $this->touchpointEntries($session),
            $this->voiceEntries($repairOrder, $session),
            $this->operationsEntries($repairOrder),
            $this->revenueEntries($repairOrder, $session),
        );

        $composed = $this->storyComposer->compose($entries);
        $pathLabels = $this->pathLabelsFromEntries($entries);
        $durationDays = $this->durationDays($session, $repairOrder);

        return new OperationalJourneyProjectionResult(
            session: $session,
            identityConfidence: $confidence,
            milestones: $composed['milestones'],
            summaryCards: $composed['summary'],
            pathLabels: $pathLabels,
            durationDays: $durationDays,
            hasStory: $composed['milestones'] !== [],
        );
    }

    private function resolveSession(RepairOrder $repairOrder): ?GrowthSession
    {
        if ($repairOrder->growth_session_id !== null) {
            return GrowthSession::query()
                ->with(['touchpoints.content', 'firstContent'])
                ->find($repairOrder->growth_session_id);
        }

        return GrowthSession::query()
            ->with(['touchpoints.content', 'firstContent'])
            ->whereHas('repairOrders', fn ($query) => $query->whereKey($repairOrder->repair_order_id))
            ->first();
    }

    /**
     * @return list<JourneyTimelineEntry>
     */
    private function growthSessionEntries(GrowthSession $session): array
    {
        $entries = [];
        $startedAt = $session->started_at ?? now();

        if (filled($session->first_search_query)) {
            $entries[] = new JourneyTimelineEntry(
                key: 'first_search',
                category: JourneyMilestoneCategory::Acquisition,
                headline: $this->acquisitionChannelLabel($session),
                detail: sprintf('Customer searched: "%s"', $session->first_search_query),
                occurredAt: $startedAt,
                sortWeight: 0,
                evidence: [
                    'search_query' => $session->first_search_query,
                    'referrer' => $session->first_referrer,
                    'utm_source' => $session->utm_source,
                ],
                evidenceItems: [
                    JourneyEvidenceFactory::item(
                        JourneyEvidenceSource::GrowthSession,
                        $startedAt,
                        'First search query recorded',
                        $session->first_search_query,
                        $session->id,
                    ),
                ],
            );
        } elseif ($this->acquisitionChannelLabel($session) !== 'Direct visit') {
            $entries[] = new JourneyTimelineEntry(
                key: 'first_contact',
                category: JourneyMilestoneCategory::Acquisition,
                headline: $this->acquisitionChannelLabel($session),
                detail: 'Customer arrived from '.$this->acquisitionChannelLabel($session).'.',
                occurredAt: $startedAt,
                sortWeight: 0,
                evidence: array_filter([
                    'referrer' => $session->first_referrer,
                    'utm_source' => $session->utm_source,
                    'utm_medium' => $session->utm_medium,
                ]),
                evidenceItems: [
                    JourneyEvidenceFactory::item(
                        JourneyEvidenceSource::GrowthSession,
                        $startedAt,
                        'Session started',
                        $session->first_referrer,
                        $session->id,
                    ),
                ],
            );
        }

        $landingTitle = $this->contentTitleForPath($session->first_landing_page, $session->firstContent);

        if ($landingTitle !== null) {
            $entries[] = new JourneyTimelineEntry(
                key: 'landing_page',
                category: JourneyMilestoneCategory::Engagement,
                headline: $landingTitle,
                detail: 'Read: '.$landingTitle,
                occurredAt: $startedAt->copy()->addSecond(),
                sortWeight: 1,
                evidence: ['path' => $session->first_landing_page],
                evidenceItems: [
                    JourneyEvidenceFactory::item(
                        JourneyEvidenceSource::GrowthSession,
                        $startedAt->copy()->addSecond(),
                        'First landing page',
                        $session->first_landing_page,
                        $session->id,
                    ),
                ],
            );
        }

        return $entries;
    }

    /**
     * @return list<JourneyTimelineEntry>
     */
    private function touchpointEntries(GrowthSession $session): array
    {
        $entries = [];
        $seenPaths = [];

        if ($session->first_landing_page !== null) {
            $seenPaths[$session->first_landing_page] = true;
        }

        foreach ($session->touchpoints as $touchpoint) {
            $path = $touchpoint->path;

            if ($touchpoint->type === GrowthTouchpointType::PageViewed && $path !== null) {
                if (isset($seenPaths[$path])) {
                    continue;
                }

                $seenPaths[$path] = true;
                $title = $this->contentTitleForPath($path, $touchpoint->content);

                if ($title === null) {
                    continue;
                }

                $entries[] = new JourneyTimelineEntry(
                    key: 'page_'.$touchpoint->id,
                    category: JourneyMilestoneCategory::Engagement,
                    headline: $title,
                    detail: 'Read: '.$title,
                    occurredAt: $touchpoint->recorded_at ?? now(),
                    sortWeight: 2,
                    evidence: ['path' => $path, 'touchpoint_id' => $touchpoint->id],
                    evidenceItems: [
                        JourneyEvidenceFactory::item(
                            JourneyEvidenceSource::GrowthTouchpoint,
                            $touchpoint->recorded_at ?? now(),
                            'Page viewed',
                            $path,
                            $touchpoint->id,
                        ),
                    ],
                );

                continue;
            }

            $milestone = match ($touchpoint->type) {
                GrowthTouchpointType::CalculatorUsed => ['Calculator', 'Used repair cost calculator'],
                GrowthTouchpointType::CallClicked => ['Called shop', 'Clicked call from website'],
                GrowthTouchpointType::SchedulerOpened => ['Scheduler', 'Opened appointment scheduler'],
                GrowthTouchpointType::AppointmentScheduled => ['Appointment', 'Scheduled appointment online'],
                GrowthTouchpointType::LeadSubmitted, GrowthTouchpointType::LeadCreated => ['Lead submitted', 'Submitted contact form'],
                GrowthTouchpointType::EstimateSubmitted => ['Estimate request', 'Submitted online estimate request'],
                default => null,
            };

            if ($milestone === null) {
                continue;
            }

            $entries[] = new JourneyTimelineEntry(
                key: 'touch_'.$touchpoint->id,
                category: match ($touchpoint->type) {
                    GrowthTouchpointType::AppointmentScheduled => JourneyMilestoneCategory::Appointment,
                    GrowthTouchpointType::CallClicked => JourneyMilestoneCategory::Voice,
                    default => JourneyMilestoneCategory::Engagement,
                },
                headline: $milestone[0],
                detail: $milestone[1],
                occurredAt: $touchpoint->recorded_at ?? now(),
                sortWeight: 3,
                evidence: ['touchpoint_type' => $touchpoint->type->value],
                evidenceItems: [
                    JourneyEvidenceFactory::item(
                        JourneyEvidenceSource::GrowthTouchpoint,
                        $touchpoint->recorded_at ?? now(),
                        $milestone[0],
                        $milestone[1],
                        $touchpoint->id,
                    ),
                ],
            );
        }

        return $entries;
    }

    /**
     * @return list<JourneyTimelineEntry>
     */
    private function voiceEntries(RepairOrder $repairOrder, GrowthSession $session): array
    {
        $entries = [];
        $calls = CallSession::query()
            ->where(function ($query) use ($repairOrder, $session): void {
                $query->where('repair_order_id', $repairOrder->repair_order_id);

                if ($repairOrder->customer_id !== null) {
                    $query->orWhere('customer_id', $repairOrder->customer_id);
                }
            })
            ->where('started_at', '>=', $session->started_at?->copy()->subDay())
            ->orderBy('started_at')
            ->with('owner')
            ->get();

        foreach ($calls as $call) {
            $advisor = $call->owner?->name ?? 'Advisor';
            $answered = $call->answered_at !== null;

            $entries[] = new JourneyTimelineEntry(
                key: 'call_'.$call->id,
                category: JourneyMilestoneCategory::Voice,
                headline: $answered ? 'Answered by '.$advisor : 'Called shop',
                detail: $answered
                    ? sprintf('Advisor %s answered the call.', $advisor)
                    : 'Customer called the shop.',
                occurredAt: $call->answered_at ?? $call->started_at ?? now(),
                sortWeight: 4,
                evidence: array_filter([
                    'call_session_id' => $call->id,
                    'advisor' => $call->owner?->name,
                    'from_number' => $call->from_number,
                ]),
                evidenceItems: [
                    JourneyEvidenceFactory::item(
                        JourneyEvidenceSource::CallSession,
                        $call->answered_at ?? $call->started_at ?? now(),
                        $answered ? 'Call answered' : 'Inbound call',
                        $answered ? 'Answered by '.$advisor : 'Customer called the shop',
                        $call->id,
                    ),
                ],
            );
        }

        return $entries;
    }

    /**
     * @return list<JourneyTimelineEntry>
     */
    private function operationsEntries(RepairOrder $repairOrder): array
    {
        $entries = [];
        $roId = $repairOrder->repair_order_id;

        foreach (CommunicationEvent::query()
            ->where('repair_order_id', $roId)
            ->orderBy('occurred_at')
            ->get() as $event) {
            if ($event->event_type === OperationalCommunicationType::EstimateViewed) {
                $at = $event->occurred_at ?? $event->created_at;
                $entries[] = new JourneyTimelineEntry(
                    key: 'estimate_view_'.$event->id,
                    category: JourneyMilestoneCategory::Estimate,
                    headline: 'Estimate viewed',
                    detail: $event->summary ?? 'Customer opened the estimate.',
                    occurredAt: $at,
                    sortWeight: 5,
                    aggregateKey: 'estimate_viewed',
                    evidence: ['communication_event_id' => $event->id],
                    evidenceItems: [
                        JourneyEvidenceFactory::item(
                            JourneyEvidenceSource::CommunicationEvent,
                            $at,
                            'Estimate viewed',
                            $event->summary,
                            $event->id,
                        ),
                    ],
                );

                continue;
            }

            if ($event->event_type === OperationalCommunicationType::EstimateSent) {
                $at = $event->occurred_at ?? $event->created_at;
                $entries[] = new JourneyTimelineEntry(
                    key: 'estimate_sent',
                    category: JourneyMilestoneCategory::Estimate,
                    headline: 'Estimate sent',
                    detail: $event->summary ?? 'Estimate sent to customer.',
                    occurredAt: $at,
                    sortWeight: 5,
                    evidence: ['communication_event_id' => $event->id],
                    evidenceItems: [
                        JourneyEvidenceFactory::item(
                            JourneyEvidenceSource::CommunicationEvent,
                            $at,
                            'Estimate sent',
                            $event->summary,
                            $event->id,
                        ),
                    ],
                );
            }
        }

        foreach (ApprovalEvent::query()
            ->where('visit_id', $roId)
            ->orderBy('approved_at')
            ->with('revocation')
            ->get() as $approval) {
            if ($approval->isRevoked()) {
                continue;
            }

            $amount = (int) ($approval->approved_amount_cents ?? 0);

            $entries[] = new JourneyTimelineEntry(
                key: 'approval_'.$approval->id,
                category: JourneyMilestoneCategory::Decision,
                headline: 'Approved',
                detail: $amount > 0
                    ? sprintf('Customer approved $%s of work.', number_format($amount / 100, 0))
                    : 'Customer approved the estimate.',
                occurredAt: $approval->approved_at ?? now(),
                sortWeight: 6,
                evidence: [
                    'approval_event_id' => $approval->id,
                    'approved_amount_cents' => $amount,
                    'source' => $approval->source?->value,
                ],
                evidenceItems: [
                    JourneyEvidenceFactory::item(
                        JourneyEvidenceSource::ApprovalEvent,
                        $approval->approved_at ?? now(),
                        'Customer approved estimate',
                        $approval->source?->value,
                        $approval->id,
                    ),
                ],
            );
        }

        foreach (Appointment::query()
            ->where('repair_order_id', $roId)
            ->orderBy('starts_at')
            ->get() as $appointment) {
            $entries[] = new JourneyTimelineEntry(
                key: 'appointment_'.$appointment->id,
                category: JourneyMilestoneCategory::Appointment,
                headline: 'Appointment scheduled',
                detail: 'Appointment booked for '.$appointment->starts_at?->format('M j, g:i A'),
                occurredAt: $appointment->starts_at ?? $appointment->created_at,
                sortWeight: 3,
                evidence: ['appointment_id' => $appointment->id],
                evidenceItems: [
                    JourneyEvidenceFactory::item(
                        JourneyEvidenceSource::Appointment,
                        $appointment->starts_at ?? $appointment->created_at,
                        'Appointment scheduled',
                        $appointment->starts_at?->format('M j, g:i A'),
                        $appointment->id,
                    ),
                ],
            );
        }

        $postedAt = $this->postedAt($repairOrder);

        if ($postedAt !== null) {
            $entries[] = new JourneyTimelineEntry(
                key: 'repair_completed',
                category: JourneyMilestoneCategory::Production,
                headline: 'Repair completed',
                detail: 'Repair order posted and closed.',
                occurredAt: $postedAt,
                sortWeight: 7,
                evidence: ['repair_order_id' => $roId],
            );
        } elseif ($repairOrder->status === RepairOrderStatus::Completed) {
            $entries[] = new JourneyTimelineEntry(
                key: 'repair_completed',
                category: JourneyMilestoneCategory::Production,
                headline: 'Repair completed',
                detail: 'Work marked complete.',
                occurredAt: $repairOrder->closed_at ?? now(),
                sortWeight: 7,
            );
        }

        return $entries;
    }

    /**
     * @return list<JourneyTimelineEntry>
     */
    private function revenueEntries(RepairOrder $repairOrder, GrowthSession $session): array
    {
        $attribution = GrowthAttribution::query()
            ->where('repair_order_id', $repairOrder->repair_order_id)
            ->latest('id')
            ->first();

        if ($attribution === null || (int) $attribution->revenue_cents <= 0) {
            return [];
        }

        return [
            new JourneyTimelineEntry(
                key: 'revenue',
                category: JourneyMilestoneCategory::Revenue,
                headline: '$'.number_format($attribution->revenue_cents / 100, 0),
                detail: 'Closed repair revenue attributed to this journey.',
                occurredAt: $repairOrder->posted_at ?? $repairOrder->closed_at ?? now(),
                sortWeight: 8,
                evidence: [
                    'growth_attribution_id' => $attribution->id,
                    'revenue_cents' => $attribution->revenue_cents,
                    'growth_session_id' => $session->id,
                ],
            ),
        ];
    }

    private function postedAt(RepairOrder $repairOrder): ?Carbon
    {
        if ($repairOrder->posted_at !== null) {
            return $repairOrder->posted_at;
        }

        $event = OperationalEvent::query()
            ->where('aggregate_type', 'repair_order')
            ->where('aggregate_id', $repairOrder->repair_order_id)
            ->where('event_name', OperationalEventName::RepairOrderPosted->value)
            ->orderByDesc('occurred_at')
            ->first();

        return $event?->occurred_at;
    }

    private function acquisitionChannelLabel(GrowthSession $session): string
    {
        if (filled($session->first_search_query)) {
            $referrer = strtolower((string) ($session->first_referrer ?? ''));

            if (str_contains($referrer, 'google.') || strtolower((string) $session->utm_source) === 'google') {
                return 'Google Search';
            }

            if (str_contains($referrer, 'bing.') || strtolower((string) $session->utm_source) === 'bing') {
                return 'Bing Search';
            }
        }

        if (filled($session->utm_source)) {
            return ucfirst(str_replace(['_', '-'], ' ', (string) $session->utm_source));
        }

        $referrer = strtolower((string) ($session->first_referrer ?? ''));

        if (str_contains($referrer, 'google.')) {
            return 'Google Search';
        }

        if (str_contains($referrer, 'facebook.') || str_contains($referrer, 'fb.')) {
            return 'Facebook';
        }

        if (str_contains($referrer, 'bing.')) {
            return 'Bing Search';
        }

        if ($referrer !== '') {
            return 'Referral';
        }

        return 'Direct visit';
    }

    private function contentTitleForPath(?string $path, ?GrowthContent $content): ?string
    {
        if ($content !== null && filled($content->title)) {
            return (string) $content->title;
        }

        if ($path === null || $path === '' || $path === '/') {
            return 'Homepage';
        }

        $slug = trim(basename($path), '/');

        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    /**
     * @param  list<JourneyTimelineEntry>  $entries
     * @return list<string>
     */
    private function pathLabelsFromEntries(array $entries): array
    {
        $labels = [];

        foreach ($entries as $entry) {
            if ($entry->category === JourneyMilestoneCategory::Acquisition) {
                $labels[] = $entry->headline;

                continue;
            }

            if (in_array($entry->category, [
                JourneyMilestoneCategory::Engagement,
                JourneyMilestoneCategory::Voice,
                JourneyMilestoneCategory::Appointment,
                JourneyMilestoneCategory::Estimate,
                JourneyMilestoneCategory::Decision,
                JourneyMilestoneCategory::Production,
            ], true)) {
                $labels[] = match ($entry->category) {
                    JourneyMilestoneCategory::Voice => 'Call',
                    JourneyMilestoneCategory::Appointment => 'Appointment',
                    JourneyMilestoneCategory::Estimate => str_contains($entry->headline, 'Viewed') ? 'Estimate viewed' : 'Estimate',
                    JourneyMilestoneCategory::Decision => 'Approved',
                    JourneyMilestoneCategory::Production => 'Repair',
                    default => $entry->headline,
                };
            }
        }

        return array_values(array_unique($labels));
    }

    private function durationDays(GrowthSession $session, RepairOrder $repairOrder): ?int
    {
        $start = $session->started_at;

        if ($start === null) {
            return null;
        }

        $end = $repairOrder->posted_at ?? $repairOrder->closed_at ?? now();

        return max(0, (int) $start->startOfDay()->diffInDays($end->startOfDay()));
    }
}
