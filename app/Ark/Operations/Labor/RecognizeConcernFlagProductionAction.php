<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Immutable flag recognition when a concern first earns previously unrecognized labor.
 *
 * Identity is the labor line — not “this concern was ever completed.”
 * Completed → reopen → Completed cannot duplicate already-recognized lines;
 * newly added labor on a later Completed can recognize without rewriting the past.
 */
final class RecognizeConcernFlagProductionAction
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
    ) {}

    /**
     * @return array{
     *     status: 'recognized'|'noop'|'deferred',
     *     recognition: ?TechnicianFlagRecognition,
     *     reason: ?string
     * }
     */
    public function handle(
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        ScopeProductionStatus $priorStatus,
        ScopeProductionStatus $newStatus,
        OperationalEvent $sourceEvent,
        ?User $actor = null,
    ): array {
        if ($newStatus !== ScopeProductionStatus::Completed) {
            return $this->result('noop', reason: 'not_completed');
        }

        if ($priorStatus === ScopeProductionStatus::Completed) {
            return $this->result('noop', reason: 'already_completed');
        }

        if (! $concern->earnsFlagRecognition()) {
            return $this->result('noop', reason: 'does_not_earn_flag_recognition');
        }

        $repairOrder->loadMissing('assignedTechnician');
        $concern->loadMissing('lines');

        $unrecognizedLines = $this->unrecognizedLaborLines($concern);

        if ($unrecognizedLines->isEmpty()) {
            return $this->result('noop', reason: 'no_unrecognized_labor');
        }

        $technicianId = $repairOrder->assigned_technician_id;

        if ($technicianId === null) {
            $this->events->record(
                OperationalEventName::FlagProductionRecognitionDeferred,
                $repairOrder,
                actor: $actor,
                payload: [
                    'concern_id' => $concern->id,
                    'reason' => 'missing_assigned_technician',
                    'source_operational_event_id' => $sourceEvent->id,
                    'unrecognized_line_ids' => $unrecognizedLines->pluck('id')->all(),
                    'unrecognized_flag_hours' => $this->sumHours($unrecognizedLines),
                    'recognition_policy' => FlagRecognitionPolicy::KEY,
                    'recognition_policy_version' => FlagRecognitionPolicy::VERSION,
                ],
            );

            return $this->result('deferred', reason: 'missing_assigned_technician');
        }

        $lineSnapshots = $unrecognizedLines->map(fn (RepairOrderLine $line): array => [
            'repair_order_line_id' => $line->id,
            'description' => mb_substr((string) $line->description, 0, 500),
            'line_type' => $line->type->value,
            'flag_hours' => round((float) $line->quantity, 2),
            'operation_id' => $line->operation_id,
        ])->values();

        $totalHours = round((float) $lineSnapshots->sum('flag_hours'), 2);

        if ($totalHours <= 0) {
            return $this->result('noop', reason: 'zero_flag_hours');
        }

        $recognition = DB::transaction(function () use (
            $repairOrder,
            $concern,
            $technicianId,
            $sourceEvent,
            $actor,
            $lineSnapshots,
            $totalHours,
        ): TechnicianFlagRecognition {
            $recognition = TechnicianFlagRecognition::query()->create([
                'repair_order_id' => $repairOrder->id,
                'repair_order_concern_id' => $concern->id,
                'technician_user_id' => $technicianId,
                'recognized_at' => $sourceEvent->occurred_at ?? now(),
                'flag_hours_total' => $totalHours,
                'source_operational_event_id' => $sourceEvent->id,
                'recognition_policy' => FlagRecognitionPolicy::KEY,
                'recognition_policy_version' => FlagRecognitionPolicy::VERSION,
                'actor_user_id' => $actor?->id,
                'technician_attribution_source' => FlagRecognitionPolicy::TECHNICIAN_ATTRIBUTION_RO_ASSIGNEE,
            ]);

            foreach ($lineSnapshots as $snapshot) {
                $recognition->lines()->create($snapshot);
            }

            return $recognition->load('lines');
        });

        $this->events->record(
            OperationalEventName::FlagProductionRecognized,
            $repairOrder,
            actor: $actor,
            payload: [
                'recognition_id' => $recognition->id,
                'concern_id' => $concern->id,
                'technician_user_id' => $recognition->technician_user_id,
                'flag_hours_total' => (float) $recognition->flag_hours_total,
                'line_ids' => $recognition->lines->pluck('repair_order_line_id')->all(),
                'source_operational_event_id' => $sourceEvent->id,
                'recognition_policy' => FlagRecognitionPolicy::KEY,
                'recognition_policy_version' => FlagRecognitionPolicy::VERSION,
                'technician_attribution_source' => FlagRecognitionPolicy::TECHNICIAN_ATTRIBUTION_RO_ASSIGNEE,
            ],
        );

        return $this->result('recognized', $recognition);
    }

    /**
     * @return Collection<int, RepairOrderLine>
     */
    private function unrecognizedLaborLines(RepairOrderConcern $concern): Collection
    {
        $alreadyRecognizedLineIds = TechnicianFlagRecognitionLine::query()
            ->whereIn(
                'repair_order_line_id',
                $concern->lines->pluck('id'),
            )
            ->pluck('repair_order_line_id')
            ->all();

        return $concern->lines
            ->filter(function (RepairOrderLine $line) use ($alreadyRecognizedLineIds): bool {
                if (! $line->type->countsTowardFlagHours()) {
                    return false;
                }

                if (in_array($line->id, $alreadyRecognizedLineIds, true)) {
                    return false;
                }

                return (float) $line->quantity > 0;
            })
            ->values();
    }

    /**
     * @param  Collection<int, RepairOrderLine>  $lines
     */
    private function sumHours(Collection $lines): float
    {
        return round((float) $lines->sum(fn (RepairOrderLine $line): float => (float) $line->quantity), 2);
    }

    /**
     * @return array{status: string, recognition: ?TechnicianFlagRecognition, reason: ?string}
     */
    private function result(
        string $status,
        ?TechnicianFlagRecognition $recognition = null,
        ?string $reason = null,
    ): array {
        return [
            'status' => $status,
            'recognition' => $recognition,
            'reason' => $reason,
        ];
    }
}
