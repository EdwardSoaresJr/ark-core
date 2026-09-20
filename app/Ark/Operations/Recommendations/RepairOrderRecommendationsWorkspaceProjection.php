<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Collection;

final class RepairOrderRecommendationsWorkspaceProjection
{
    /**
     * @return array{
     *     recommendations: Collection<int, array<string, mixed>>,
     *     open_count: int,
     *     safety_count: int,
     *     follow_up_due_count: int,
     *     estimated_cents: int,
     *     current_mileage: ?int,
     *     decision_reasons: list<array{value: string, label: string}>,
     *     resolved_reasons: list<array{value: string, label: string}>,
     *     due_kinds: list<array{value: string, label: string}>,
     *     urgencies: list<array{value: string, label: string}>,
     * }
     */
    public static function for(RepairOrder $repairOrder, ?User $actor = null): array
    {
        $summary = VehicleRecommendationsProjection::forRepairOrder($repairOrder);
        $mileage = $summary['current_mileage'];

        $rows = $summary['open']->map(
            fn (Recommendation $recommendation): array => self::row($recommendation, $repairOrder, $mileage),
        );

        return [
            'recommendations' => $rows,
            'open_count' => $summary['open_count'],
            'safety_count' => $summary['safety_count'],
            'follow_up_due_count' => $summary['follow_up_due_count'],
            'estimated_cents' => $summary['estimated_cents'],
            'current_mileage' => $mileage,
            'decision_reasons' => collect(RecommendationDecisionReason::cases())
                ->map(fn (RecommendationDecisionReason $reason): array => [
                    'value' => $reason->value,
                    'label' => $reason->label(),
                ])
                ->all(),
            'resolved_reasons' => collect(RecommendationResolvedReason::cases())
                ->map(fn (RecommendationResolvedReason $reason): array => [
                    'value' => $reason->value,
                    'label' => $reason->label(),
                ])
                ->all(),
            'due_kinds' => collect(RecommendationDueKind::cases())
                ->map(fn (RecommendationDueKind $kind): array => [
                    'value' => $kind->value,
                    'label' => $kind->label(),
                ])
                ->all(),
            'urgencies' => collect(RecommendationUrgency::cases())
                ->map(fn (RecommendationUrgency $urgency): array => [
                    'value' => $urgency->value,
                    'label' => $urgency->label(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Recommendation $recommendation, RepairOrder $repairOrder, ?int $currentMileage): array
    {
        $recommendation->loadMissing(['events.actor', 'estimateLinks', 'originatingInspectionItem', 'originatingConcern', 'followUpOwner']);

        $lastPresentation = $recommendation->lastPresentation();
        $lastDecision = $recommendation->lastDecision();
        $onEstimate = $recommendation->isOnRepairOrder($repairOrder);
        $link = $recommendation->estimateLinkForRepairOrder($repairOrder);

        return [
            'id' => $recommendation->id,
            'title' => $recommendation->title,
            'customer_description' => $recommendation->customer_description,
            'advisor_note' => $recommendation->advisor_note,
            'urgency' => $recommendation->urgency->value,
            'urgency_label' => $recommendation->urgency->label(),
            'safety_related' => $recommendation->safety_related,
            'discovered_at' => $recommendation->discovered_at,
            'discovered_mileage' => $recommendation->discovered_mileage,
            'due_kind' => $recommendation->due_kind->value,
            'due_kind_label' => $recommendation->due_kind->label(),
            'due_on' => $recommendation->due_on,
            'due_mileage' => $recommendation->due_mileage,
            'service_due' => $recommendation->isServiceDue($currentMileage),
            'follow_up_at' => $recommendation->follow_up_at,
            'follow_up_owner_name' => $recommendation->followUpOwner?->name,
            'follow_up_due' => $recommendation->followUpIsDue(),
            'follow_up_snoozed_until' => $recommendation->follow_up_snoozed_until,
            'source_kind' => $recommendation->source_kind?->label(),
            'source_finding_label' => $recommendation->originatingInspectionItem?->label,
            'on_current_estimate' => $onEstimate,
            'current_concern_id' => $link?->repair_order_concern_id,
            'estimated_cents' => $recommendation->latestEstimatedAmountCents(),
            'last_presentation_at' => $lastPresentation?->occurred_at,
            'last_decision' => $lastDecision?->type->label(),
            'last_decision_type' => $lastDecision?->type->value,
            'last_decision_reason' => $lastDecision?->reasonLabel(),
            'was_presented' => $recommendation->wasPresented(),
            'history' => $recommendation->events->map(fn (RecommendationEvent $event): array => [
                'id' => $event->id,
                'type' => $event->type->value,
                'label' => $event->type->label(),
                'occurred_at' => $event->occurred_at,
                'amount_cents' => $event->amount_cents,
                'reason' => $event->reasonLabel(),
                'note' => $event->note,
                'actor' => $event->actor?->name,
            ])->values()->all(),
        ];
    }
}
