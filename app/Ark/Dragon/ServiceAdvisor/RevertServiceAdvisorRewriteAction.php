<?php

namespace App\Ark\Dragon\ServiceAdvisor;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RevertServiceAdvisorRewriteAction
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly EstimateDocumentService $documents,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        DragonServiceAdvisorApplication $application,
        User $actor,
    ): DragonServiceAdvisorApplication {
        abort_unless((int) $application->repair_order_id === (int) $repairOrder->id, 404);
        abort_unless($application->isApplied(), 422, 'Nothing to revert.');

        $repairOrder->ensureOpenForEditing();
        $application->loadMissing(['concern', 'repairOrderLine']);
        $field = $application->field;
        $original = $application->original_text;

        return DB::transaction(function () use ($repairOrder, $application, $actor, $field, $original): DragonServiceAdvisorApplication {
            if ($field->isConcernNarrative()) {
                $concern = $application->concern;
                abort_if($concern === null, 404);
                match ($field) {
                    ServiceAdvisorField::Summary => $concern->summary = $original,
                    ServiceAdvisorField::CustomerStates => $concern->customer_states = $original,
                    ServiceAdvisorField::VerifiedFindings => $concern->verified_findings = $original,
                    ServiceAdvisorField::DtcsSummary => $concern->dtcs_summary = $original,
                    ServiceAdvisorField::Recommendation => $concern->recommendation = $original,
                    default => throw new RuntimeException('Not a concern narrative field.'),
                };
                $concern->save();
            } elseif ($field === ServiceAdvisorField::VisitReason) {
                $repairOrder->visit_reason = $original;
                $repairOrder->save();
            } elseif ($field === ServiceAdvisorField::LineNote) {
                $line = $application->repairOrderLine;
                abort_if($line === null, 404);
                abort_unless($line->type->isNote(), 422);
                $line->description = $original;
                $line->save();
            } else {
                throw new RuntimeException('Unsupported rewrite field.');
            }

            $application->forceFill([
                'reverted_at' => Carbon::now(),
                'reverted_by_user_id' => $actor->id,
            ])->save();

            $this->documents->markDirtyForRepairOrder($repairOrder);
            $this->recordRepairOrderEstimateMutation($repairOrder, $actor);

            return $application->fresh();
        });
    }
}
