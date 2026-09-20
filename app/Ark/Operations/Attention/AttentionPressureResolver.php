<?php

namespace App\Ark\Operations\Attention;

use App\Ark\Operations\Observations\OperationalObservation;
use App\Ark\Operations\Observations\OperationalObservationSeverity;
use App\Ark\Operations\Observations\OperationalObservationType;

/**
 * Explainable attention — every point in the score maps to a visible reason.
 */
final class AttentionPressureResolver
{
    /**
     * @param  list<OperationalObservation>  $observations
     */
    public function candidate(
        string $entityKey,
        string $headline,
        array $observations,
        ?int $conversationId = null,
        ?int $customerId = null,
        ?int $repairOrderId = null,
    ): ?AttentionCandidate {
        if ($observations === []) {
            return null;
        }

        $reasons = [];
        $score = 0;

        foreach ($observations as $observation) {
            $explanation = $this->explain($observation);

            if ($explanation === null) {
                continue;
            }

            $reasons[] = $explanation->label;
            $score += $explanation->weight;
        }

        if ($reasons === []) {
            return null;
        }

        return new AttentionCandidate(
            entityKey: $entityKey,
            headline: $headline,
            pressureScore: min(100, max(0, $score)),
            reasons: $reasons,
            observations: $observations,
            conversationId: $conversationId,
            customerId: $customerId,
            repairOrderId: $repairOrderId,
        );
    }

    private function explain(OperationalObservation $observation): ?AttentionReason
    {
        return match ($observation->type) {
            OperationalObservationType::CustomerWaitingResponse => new AttentionReason(
                label: $this->waitingReasonLabel($observation),
                weight: $this->severityWeight($observation->severity, low: 12, medium: 24, high: 36),
                observationType: $observation->type,
            ),
            OperationalObservationType::CustomerSentMultipleMessages => new AttentionReason(
                label: ($observation->metadata['message_count'] ?? 2).' customer messages',
                weight: $this->severityWeight($observation->severity, low: 18, medium: 28, high: 38),
                observationType: $observation->type,
            ),
            OperationalObservationType::ConversationUnassigned => new AttentionReason(
                label: 'Conversation unassigned',
                weight: 20,
                observationType: $observation->type,
            ),
            OperationalObservationType::EstimateViewedMultipleTimes => new AttentionReason(
                label: 'Estimate viewed '.($observation->metadata['view_count'] ?? 'multiple').' times',
                weight: $this->severityWeight($observation->severity, low: 16, medium: 26, high: 34),
                observationType: $observation->type,
            ),
            OperationalObservationType::EstimateViewed => new AttentionReason(
                label: 'Estimate viewed',
                weight: 14,
                observationType: $observation->type,
            ),
            OperationalObservationType::EstimateSent => new AttentionReason(
                label: 'Estimate sent — awaiting customer',
                weight: 10,
                observationType: $observation->type,
            ),
            default => null,
        };
    }

    private function waitingReasonLabel(OperationalObservation $observation): string
    {
        $hours = (int) ($observation->metadata['hours_waiting'] ?? 0);

        if ($hours >= 24) {
            $days = (int) floor($hours / 24);

            return "Customer waiting {$days} day".($days === 1 ? '' : 's');
        }

        if ($hours >= 1) {
            return "Customer waiting {$hours} hour".($hours === 1 ? '' : 's');
        }

        return 'Customer waiting response';
    }

    private function severityWeight(
        OperationalObservationSeverity $severity,
        int $low,
        int $medium,
        int $high,
    ): int {
        return match ($severity) {
            OperationalObservationSeverity::High => $high,
            OperationalObservationSeverity::Medium => $medium,
            OperationalObservationSeverity::Low => $low,
        };
    }
}
