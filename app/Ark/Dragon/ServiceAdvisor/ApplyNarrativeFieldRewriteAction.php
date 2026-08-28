<?php

namespace App\Ark\Dragon\ServiceAdvisor;

use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Shared write path for Dragon rewrites (concern narrative, visit reason, line notes).
 * Always creates a DragonServiceAdvisorApplication audit row.
 */
final class ApplyNarrativeFieldRewriteAction
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly EstimateDocumentService $documents,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        ServiceAdvisorField $field,
        string $originalText,
        string $originalHash,
        string $proposalText,
        User $actor,
        ?RepairOrderConcern $concern = null,
        ?RepairOrderLine $line = null,
        ?DragonAssistRequest $assist = null,
        ?string $editedProposal = null,
        ?int $openedEstimateVersion = null,
        ServiceAdvisorMode $mode = ServiceAdvisorMode::ServiceAdvisorRewrite,
    ): DragonServiceAdvisorApplication {
        $repairOrder->ensureOpenForEditing();

        if ($openedEstimateVersion !== null && (int) $openedEstimateVersion !== (int) $repairOrder->estimate_version) {
            $lastActorId = (int) $repairOrder->estimate_version_actor_id;
            if ((int) $actor->id !== $lastActorId) {
                throw new RuntimeException('This note changed after Dragon generated the rewrite. Refresh before applying.');
            }
        }

        if ($field->isConcernNarrative()) {
            abort_if($concern === null, 404);
            abort_unless($concern->repair_order_id === $repairOrder->id, 404);
            $concern->refresh();
            $currentText = $this->readConcernField($concern, $field);
        } elseif ($field === ServiceAdvisorField::VisitReason) {
            $repairOrder->refresh();
            $currentText = (string) ($repairOrder->visit_reason ?? '');
        } elseif ($field === ServiceAdvisorField::LineNote) {
            abort_if($line === null, 404);
            abort_unless((int) $line->repair_order_id === (int) $repairOrder->id, 404);
            abort_unless($line->type->isNote(), 422, 'Only note lines can be rewritten.');
            $line->refresh();
            $currentText = (string) ($line->description ?? '');
        } else {
            throw new RuntimeException('Unsupported rewrite field.');
        }

        if (ServiceAdvisorContextBuilder::hashText($currentText) !== $originalHash) {
            throw new RuntimeException('This note changed after Dragon generated the rewrite. Refresh before applying.');
        }

        $proposal = trim($proposalText);
        if ($proposal === '') {
            throw new RuntimeException('Dragon proposal is missing.');
        }

        $edited = filled($editedProposal) ? trim((string) $editedProposal) : null;
        $toApply = $edited ?? $proposal;

        return DB::transaction(function () use (
            $repairOrder,
            $concern,
            $line,
            $assist,
            $actor,
            $field,
            $mode,
            $originalText,
            $originalHash,
            $proposal,
            $edited,
            $toApply,
        ): DragonServiceAdvisorApplication {
            if ($field->isConcernNarrative()) {
                $this->writeConcernField($concern, $field, $toApply);
                $concern->save();
            } elseif ($field === ServiceAdvisorField::VisitReason) {
                $repairOrder->visit_reason = $toApply;
                $repairOrder->save();
            } else {
                $line->description = $toApply;
                $line->save();
            }

            $this->documents->markDirtyForRepairOrder($repairOrder);
            $this->recordRepairOrderEstimateMutation($repairOrder, $actor);

            return DragonServiceAdvisorApplication::query()->create([
                'repair_order_id' => $repairOrder->id,
                'concern_id' => $concern?->id ?? $line?->repair_order_concern_id,
                'repair_order_line_id' => $line?->id,
                'field' => $field,
                'original_text' => $originalText,
                'proposal_text' => $proposal,
                'user_edited_proposal' => $edited,
                'applied_text' => $toApply,
                'original_hash' => $originalHash,
                'dragon_assist_request_id' => $assist?->id,
                'model_name' => $assist?->result?->model_name,
                'mode' => $mode,
                'applied_by_user_id' => $actor->id,
                'applied_at' => Carbon::now(),
            ]);
        });
    }

    public function readConcernField(RepairOrderConcern $concern, ServiceAdvisorField $field): string
    {
        return match ($field) {
            ServiceAdvisorField::Summary => (string) ($concern->summary ?? ''),
            ServiceAdvisorField::CustomerStates => (string) ($concern->customer_states ?? ''),
            ServiceAdvisorField::VerifiedFindings => (string) ($concern->verified_findings ?? ''),
            ServiceAdvisorField::DtcsSummary => (string) ($concern->dtcs_summary ?? ''),
            ServiceAdvisorField::Recommendation => (string) ($concern->recommendation ?? ''),
            default => throw new RuntimeException('Not a concern narrative field.'),
        };
    }

    private function writeConcernField(RepairOrderConcern $concern, ServiceAdvisorField $field, string $value): void
    {
        match ($field) {
            ServiceAdvisorField::Summary => $concern->summary = $value,
            ServiceAdvisorField::CustomerStates => $concern->customer_states = $value,
            ServiceAdvisorField::VerifiedFindings => $concern->verified_findings = $value,
            ServiceAdvisorField::DtcsSummary => $concern->dtcs_summary = $value,
            ServiceAdvisorField::Recommendation => $concern->recommendation = $value,
            default => throw new RuntimeException('Not a concern narrative field.'),
        };
    }
}
