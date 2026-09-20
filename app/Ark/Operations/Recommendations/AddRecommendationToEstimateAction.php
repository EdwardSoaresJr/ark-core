<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\RepairOrders\RepairActionOwnerType;
use App\Ark\Operations\RepairOrders\RepairActionStatus;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkflowStatus;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AddRecommendationToEstimateAction
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly RecordRecommendationEventAction $events,
        private readonly EstimateTotalsCalculator $calculator,
        private readonly RepairOrderLifecycleTransition $lifecycle,
    ) {}

    /**
     * @return array{recommendation: Recommendation, concern: RepairOrderConcern, created: bool}
     */
    public function handle(Recommendation $recommendation, RepairOrder $repairOrder, User $actor): array
    {
        $repairOrder->ensureOpenForEditing();

        if (! $recommendation->isOpen()) {
            throw ValidationException::withMessages([
                'recommendation' => 'Only open recommendations can be added to an estimate.',
            ]);
        }

        if ((int) $recommendation->vehicle_id !== (int) $repairOrder->vehicle_id
            || (int) $recommendation->customer_id !== (int) $repairOrder->customer_id) {
            throw ValidationException::withMessages([
                'recommendation' => 'That recommendation does not belong to this vehicle.',
            ]);
        }

        $recommendation->loadMissing(['estimateLinks.concern.lines', 'originatingConcern.lines', 'originatingConcern.workGroups.lines']);

        $existing = $recommendation->estimateLinkForRepairOrder($repairOrder);

        if ($existing instanceof RecommendationEstimateLink) {
            $existing->loadMissing('concern');

            return [
                'recommendation' => $recommendation,
                'concern' => $existing->concern,
                'created' => false,
            ];
        }

        return DB::transaction(function () use ($recommendation, $repairOrder, $actor): array {
            $source = $this->sourceConcern($recommendation);
            $sourceSnapshot = $source instanceof RepairOrderConcern
                ? $this->snapshotConcern($source)
                : null;

            $repairOrder->loadMissing('customer');
            $position = ((int) $repairOrder->concerns()->max('position')) + 1;
            $concern = RepairOrderConcern::query()->create([
                'repair_order_id' => $repairOrder->id,
                'summary' => $source?->summary ?: $recommendation->title,
                'customer_states' => $source?->customer_states,
                'verified_findings' => $source?->verified_findings,
                'dtcs_summary' => $source?->dtcs_summary,
                'recommendation' => $source?->recommendation ?: $recommendation->customer_description,
                'disposition' => RepairOrderConcernDisposition::Recommended,
                'production_status' => ScopeProductionStatus::Pending,
                'billing_posture' => $source?->billing_posture
                    ?? ConcernBillingPosture::defaultForCustomerTag($repairOrder->customer?->customer_type),
                'recommendation_intent' => $source?->recommendation_intent?->value ?? 'maintenance',
                'position' => max(1, $position),
            ]);

            if ($sourceSnapshot !== null) {
                $this->copyWork($sourceSnapshot, $concern, $repairOrder);
            }

            $concern->load('lines');
            $amountCents = (int) $concern->lines->sum(fn (RepairOrderLine $line): int => (int) $line->total_cents);

            RecommendationEstimateLink::query()->create([
                'recommendation_id' => $recommendation->id,
                'repair_order_id' => $repairOrder->id,
                'repair_order_concern_id' => $concern->id,
                'amount_cents' => $amountCents > 0 ? $amountCents : null,
            ]);

            $this->events->handle(
                $recommendation,
                RecommendationEventType::AddedToEstimate,
                actor: $actor,
                repairOrder: $repairOrder,
                concern: $concern,
                amountCents: $amountCents > 0 ? $amountCents : null,
                payload: [
                    'source_concern_id' => $source?->id,
                    'source_repair_order_id' => $source?->repair_order_id,
                ],
            );

            $this->calculator->recalculateRepairOrder($repairOrder);

            if (RepairOrderWorkflowStatus::from($repairOrder->status)->is(RepairOrderStatus::Draft)) {
                $this->lifecycle->move($repairOrder, RepairOrderStatus::Estimate, $actor);
            }

            $this->recordRepairOrderEstimateMutation($repairOrder, $actor);

            return [
                'recommendation' => $recommendation->fresh(['estimateLinks', 'events']) ?? $recommendation,
                'concern' => $concern->fresh(['lines', 'workGroups.lines']) ?? $concern,
                'created' => true,
            ];
        });
    }

    private function sourceConcern(Recommendation $recommendation): ?RepairOrderConcern
    {
        $latestLink = $recommendation->estimateLinks->sortByDesc('id')->first();

        if ($latestLink?->concern instanceof RepairOrderConcern) {
            return $latestLink->concern;
        }

        return $recommendation->originatingConcern;
    }

    /**
     * @return array{concern: RepairOrderConcern, groups: array<int, array{group: \App\Ark\Operations\RepairOrders\RepairOrderWorkGroup, lines: \Illuminate\Support\Collection<int, RepairOrderLine>}>, ungrouped: \Illuminate\Support\Collection<int, RepairOrderLine>}
     */
    private function snapshotConcern(RepairOrderConcern $source): array
    {
        $source->loadMissing(['lines', 'workGroups.lines']);

        $groups = [];
        foreach ($source->workGroups as $group) {
            $groups[] = [
                'group' => $group,
                'lines' => $group->lines,
            ];
        }

        return [
            'concern' => $source,
            'groups' => $groups,
            'ungrouped' => $source->lines->whereNull('repair_order_work_group_id')->values(),
        ];
    }

    /**
     * @param  array{concern: RepairOrderConcern, groups: array<int, array{group: \App\Ark\Operations\RepairOrders\RepairOrderWorkGroup, lines: \Illuminate\Support\Collection<int, RepairOrderLine>}>, ungrouped: \Illuminate\Support\Collection<int, RepairOrderLine>}  $snapshot
     */
    private function copyWork(array $snapshot, RepairOrderConcern $target, RepairOrder $repairOrder): void
    {
        foreach ($snapshot['groups'] as $groupSnapshot) {
            $newGroup = $target->workGroups()->create([
                'title' => $groupSnapshot['group']->title,
                'position' => $groupSnapshot['group']->position,
                'owner_type' => RepairActionOwnerType::Technician,
                'owner_user_id' => null,
                'status' => RepairActionStatus::Pending,
                'latest_update' => null,
                'created_from_template_id' => null,
            ]);

            foreach ($groupSnapshot['lines'] as $line) {
                $this->copyLine($line, $target, $repairOrder, $newGroup->id);
            }
        }

        foreach ($snapshot['ungrouped'] as $line) {
            $this->copyLine($line, $target, $repairOrder, null);
        }
    }

    private function copyLine(
        RepairOrderLine $source,
        RepairOrderConcern $target,
        RepairOrder $repairOrder,
        ?int $workGroupId,
    ): RepairOrderLine {
        $clone = $source->replicate([
            'legacy_arksms_line_id',
            'dealer_quote_line_id',
        ]);
        $clone->repair_order_id = $repairOrder->id;
        $clone->repair_order_concern_id = $target->id;
        $clone->repair_order_work_group_id = $workGroupId;
        $clone->procurement_state = PartProcurementState::None;
        $clone->dealer_quote_line_id = null;
        $clone->save();

        return $clone;
    }
}
