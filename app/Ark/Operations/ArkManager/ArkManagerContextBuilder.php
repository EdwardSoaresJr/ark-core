<?php

namespace App\Ark\Operations\ArkManager;

use App\Ark\Operations\Flow\FlowStageProjection;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Today\AdvisorTodayProjection;
use App\Ark\Operations\Today\TodayCommitmentRow;
use App\Ark\Operations\Today\TodayPipelineInventoryQuery;
use App\Ark\Operations\Today\TodayPipelineMetric;
use App\Ark\Operations\Today\TodayRecommendation;

final class ArkManagerContextBuilder
{
    public function fromToday(AdvisorTodayProjection $today): ArkManagerContext
    {
        $constraint = $today->flow->constraint;

        return new ArkManagerContext(
            shopDate: ShopDisplayTimezone::now()->toDateString(),
            pipeline: array_map(
                fn (TodayPipelineMetric $metric): array => [
                    'key' => $metric->key,
                    'label' => $metric->label,
                    'amount_label' => $metric->amountLabel,
                    'amount_cents' => $metric->amountCents,
                    'repair_order_count' => $metric->repairOrderCount,
                    'emphasized' => $metric->emphasized,
                ],
                $today->pipeline,
            ),
            flowConstraint: $constraint === null ? null : [
                'stage_key' => $constraint->stageKey->value,
                'label' => $constraint->label,
                'headline' => $constraint->headline,
                'reasons' => $constraint->reasons,
            ],
            flowStages: array_map(
                fn (FlowStageProjection $stage): array => [
                    'stage_key' => $stage->stageKey->value,
                    'label' => $stage->label,
                    'count' => $stage->count,
                    'revenue_label' => $stage->revenueLabel,
                    'revenue_cents' => $stage->revenueCents,
                    'oldest_age_label' => $stage->oldestAgeLabel,
                    'median_age_label' => $stage->medianAgeLabel,
                    'signal_summary' => $stage->signalSummary,
                ],
                $today->flow->displayStages(),
            ),
            recommendations: array_map(
                fn (TodayRecommendation $recommendation): array => [
                    'rule_key' => $recommendation->ruleKey,
                    'title' => $recommendation->title,
                    'why_reasons' => $recommendation->whyReasons,
                    'impact_kind' => $recommendation->impactKind->value,
                    'impact_label' => $recommendation->impactLabel,
                    'suggested_action' => $recommendation->suggestedAction,
                    'repair_order_id' => $recommendation->repairOrderId,
                    'customer_name' => $recommendation->customerName,
                ],
                array_slice($today->recommendations, 0, 8),
            ),
            commitments: array_map(
                fn (TodayCommitmentRow $row): array => [
                    'title' => $row->title,
                    'due_label' => $row->dueLabel,
                    'reason' => $row->reason,
                    'owner_name' => $row->ownerName,
                    'shop_repair_order_id' => $row->shopRepairOrderId,
                    'is_overdue' => $row->isOverdue,
                ],
                $today->commitments->rows,
            ),
        );
    }

    public function recommendedFocus(ArkManagerContext $context): string
    {
        if ($context->flowConstraint !== null) {
            return match ($context->flowConstraint['stage_key']) {
                'waiting_approval' => 'Approval follow-up',
                'waiting_parts' => 'Parts resolution and customer updates',
                'needs_diagnosis', 'building_estimate' => 'Move intake into written estimates',
                'ready_pickup' => 'Pickup and final payment collection',
                'in_repair' => 'Keep approved work moving through the bay',
                'quality_check' => 'Complete QC and release vehicles',
                default => 'Clear the primary operational constraint',
            };
        }

        $top = $context->recommendations[0]['suggested_action'] ?? 'Review open repair orders';

        return is_string($top) ? $top : 'Review open repair orders';
    }

    public function revenueInFlightLabel(ArkManagerContext $context): ?string
    {
        foreach ($context->pipeline as $metric) {
            if (($metric['key'] ?? null) === TodayPipelineInventoryQuery::REVENUE_IN_FLIGHT) {
                return is_string($metric['amount_label'] ?? null) ? $metric['amount_label'] : null;
            }
        }

        return null;
    }
}
