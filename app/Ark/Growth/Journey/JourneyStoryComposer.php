<?php

namespace App\Ark\Growth\Journey;

use Illuminate\Support\Carbon;

final class JourneyStoryComposer
{
    /**
     * @param  list<JourneyTimelineEntry>  $entries
     * @return array{milestones: list<JourneyMilestone>, summary: list<array{key: string, label: string, value: string, meta: string|null}>}
     */
    public function compose(array $entries): array
    {
        usort($entries, static function (JourneyTimelineEntry $a, JourneyTimelineEntry $b): int {
            $time = $a->occurredAt <=> $b->occurredAt;

            return $time !== 0 ? $time : $a->sortWeight <=> $b->sortWeight;
        });

        $milestones = [];
        $estimateViews = [];
        $firstEstimateSentAt = null;
        $firstApprovalAt = null;
        $approvalAmountCents = null;

        foreach ($entries as $entry) {
            if ($entry->aggregateKey === 'estimate_viewed') {
                $estimateViews[] = $entry;

                continue;
            }

            if ($entry->key === 'estimate_sent' && $firstEstimateSentAt === null) {
                $firstEstimateSentAt = $entry->occurredAt;
            }

            if ($entry->category === JourneyMilestoneCategory::Decision && $firstApprovalAt === null) {
                $firstApprovalAt = $entry->occurredAt;
                $approvalAmountCents = (int) ($entry->evidence['approved_amount_cents'] ?? 0);
            }

            $milestones[] = $this->toMilestone($entry);
        }

        if ($estimateViews !== []) {
            $milestones[] = $this->aggregateEstimateViews($estimateViews, $firstApprovalAt);
            usort($milestones, static fn (JourneyMilestone $a, JourneyMilestone $b): int => $a->occurredAt <=> $b->occurredAt);
        }

        return [
            'milestones' => $milestones,
            'summary' => $this->summaryCards($milestones, $firstEstimateSentAt, $firstApprovalAt, $approvalAmountCents),
        ];
    }

    /**
     * @param  list<JourneyTimelineEntry>  $views
     */
    private function aggregateEstimateViews(array $views, ?Carbon $approvedAt): JourneyMilestone
    {
        $count = count($views);
        $first = $views[0];
        $last = $views[array_key_last($views)];
        $headline = $count === 1
            ? 'Viewed estimate'
            : sprintf('Viewed estimate %d×', $count);

        $detail = $count === 1
            ? $first->detail
            : sprintf(
                'Customer opened the estimate %d times between %s and %s.',
                $count,
                $first->occurredAt->format('M j, g:i A'),
                $last->occurredAt->format('M j, g:i A'),
            );

        $metrics = ['view_count' => $count];

        if ($approvedAt !== null && $last->occurredAt->lessThanOrEqualTo($approvedAt)) {
            $minutes = (int) $last->occurredAt->diffInMinutes($approvedAt);
            $metrics['minutes_to_approval'] = max(0, $minutes);
            $detail = $count === 1
                ? 'Customer viewed the estimate once before approving.'
                : sprintf('Customer viewed the estimate %d times before approving.', $count);
        }

        return new JourneyMilestone(
            key: 'estimate_viewed_aggregate',
            category: JourneyMilestoneCategory::Estimate,
            headline: $headline,
            detail: $detail,
            occurredAt: $last->occurredAt,
            evidence: ['view_count' => $count],
            metrics: $metrics,
            evidenceItems: array_merge(...array_map(
                static fn (JourneyTimelineEntry $view): array => $view->evidenceItems,
                $views,
            )),
        );
    }

    private function toMilestone(JourneyTimelineEntry $entry): JourneyMilestone
    {
        return new JourneyMilestone(
            key: $entry->key,
            category: $entry->category,
            headline: $entry->headline,
            detail: $entry->detail,
            occurredAt: $entry->occurredAt,
            evidence: $entry->evidence,
            evidenceItems: $entry->evidenceItems,
        );
    }

    /**
     * @param  list<JourneyMilestone>  $milestones
     * @return list<array{key: string, label: string, value: string, meta: string|null}>
     */
    private function summaryCards(
        array $milestones,
        ?Carbon $firstEstimateSentAt,
        ?Carbon $firstApprovalAt,
        ?int $approvalAmountCents,
    ): array {
        $cards = [];

        foreach ($milestones as $milestone) {
            $card = match ($milestone->category) {
                JourneyMilestoneCategory::Acquisition => ['key' => 'first_contact', 'label' => 'First Contact'],
                JourneyMilestoneCategory::Engagement => ['key' => 'landing_page', 'label' => 'Landing Page'],
                JourneyMilestoneCategory::Voice => ['key' => 'call', 'label' => 'Call'],
                JourneyMilestoneCategory::Appointment => ['key' => 'appointment', 'label' => 'Appointment'],
                JourneyMilestoneCategory::Estimate => ['key' => 'estimate', 'label' => 'Estimate'],
                JourneyMilestoneCategory::Decision => ['key' => 'decision', 'label' => 'Decision'],
                JourneyMilestoneCategory::Production => ['key' => 'repair', 'label' => 'Repair'],
                JourneyMilestoneCategory::Revenue => ['key' => 'revenue', 'label' => 'Revenue'],
                JourneyMilestoneCategory::Review => ['key' => 'review', 'label' => 'Review'],
                default => null,
            };

            if ($card === null) {
                continue;
            }

            $cards[$card['key']] = [
                'key' => $card['key'],
                'label' => $card['label'],
                'value' => $milestone->headline,
                'meta' => $milestone->occurredAt->format('M j • g:i A'),
                'milestone_key' => $milestone->key,
                'expandable' => $milestone->expandable(),
                'evidence_items' => array_map(
                    static fn (JourneyEvidenceItem $item): array => $item->toArray(),
                    $milestone->evidenceItems,
                ),
            ];
        }

        if (isset($cards['estimate']) && isset($cards['estimate']['value']) && str_contains($cards['estimate']['value'], 'Viewed')) {
            // already aggregated headline
        } elseif ($firstEstimateSentAt !== null && ! isset($cards['estimate'])) {
            $cards['estimate'] = [
                'key' => 'estimate',
                'label' => 'Estimate',
                'value' => 'Sent to customer',
                'meta' => $firstEstimateSentAt->format('M j • g:i A'),
            ];
        }

        if ($firstApprovalAt !== null && isset($cards['decision'])) {
            $minutes = isset($cards['estimate']['meta'])
                ? ($milestones[array_key_last(array_filter($milestones, fn (JourneyMilestone $m) => $m->key === 'estimate_viewed_aggregate'))] ?? null)?->metrics['minutes_to_approval'] ?? null
                : null;

            if ($minutes !== null && $minutes > 0) {
                $cards['decision']['value'] = sprintf('Approved after %d minutes', $minutes);
            }
        }

        if ($approvalAmountCents !== null && $approvalAmountCents > 0 && ! isset($cards['revenue'])) {
            $cards['revenue'] = [
                'key' => 'revenue',
                'label' => 'Revenue',
                'value' => '$'.number_format($approvalAmountCents / 100, 0),
                'meta' => null,
            ];
        }

        return array_values($cards);
    }
}
