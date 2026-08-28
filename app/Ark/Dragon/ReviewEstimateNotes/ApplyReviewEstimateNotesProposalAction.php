<?php

namespace App\Ark\Dragon\ReviewEstimateNotes;

use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Dragon\Assist\DragonAssistStatus;
use App\Ark\Dragon\Assist\DragonAssistTaskType;
use App\Ark\Dragon\ServiceAdvisor\ApplyNarrativeFieldRewriteAction;
use App\Ark\Dragon\ServiceAdvisor\DragonServiceAdvisorApplication;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorField;
use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorMode;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Models\User;
use RuntimeException;

final class ApplyReviewEstimateNotesProposalAction
{
    public function __construct(
        private readonly ApplyNarrativeFieldRewriteAction $applyField,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        DragonAssistRequest $assist,
        ServiceAdvisorField $field,
        User $actor,
        ?int $concernId = null,
        ?int $lineId = null,
        ?string $editedProposal = null,
        ?int $openedEstimateVersion = null,
    ): DragonServiceAdvisorApplication {
        abort_unless((int) $assist->repair_order_id === (int) $repairOrder->id, 404);
        abort_unless($assist->task_type === DragonAssistTaskType::ReviewEstimateNotes, 422);
        abort_unless($assist->status === DragonAssistStatus::Completed, 422, 'Dragon review is not ready.');

        $assist->loadMissing('result');
        $proposals = $assist->result?->result_json['proposals'] ?? [];
        $match = $this->findProposal($proposals, $field, $concernId, $lineId);

        if ($match === null) {
            throw new RuntimeException('That proposal is not part of this Dragon review.');
        }

        if (! ($match['applyable'] ?? false)) {
            throw new RuntimeException($match['rejected_reason'] ?? 'This proposal cannot be applied.');
        }

        $originalText = (string) ($match['original_text'] ?? '');
        $originalHash = (string) ($match['original_hash'] ?? '');
        $proposal = (string) ($match['proposed_text'] ?? '');

        if ($originalHash === '' || $proposal === '') {
            throw new RuntimeException('Proposal is incomplete.');
        }

        $concern = null;
        $line = null;

        if ($field->isConcernNarrative()) {
            $concern = RepairOrderConcern::query()
                ->where('repair_order_id', $repairOrder->id)
                ->whereKey((int) ($match['concern_id'] ?? 0))
                ->firstOrFail();
        } elseif ($field === ServiceAdvisorField::LineNote) {
            $line = RepairOrderLine::query()
                ->where('repair_order_id', $repairOrder->id)
                ->whereKey((int) ($match['line_id'] ?? 0))
                ->firstOrFail();
        }

        return $this->applyField->execute(
            $repairOrder,
            $field,
            $originalText,
            $originalHash,
            $proposal,
            $actor,
            concern: $concern,
            line: $line,
            assist: $assist,
            editedProposal: $editedProposal,
            openedEstimateVersion: $openedEstimateVersion,
            mode: ServiceAdvisorMode::ServiceAdvisorRewrite,
        );
    }

    /**
     * @param  list<mixed>  $proposals
     * @return array<string, mixed>|null
     */
    private function findProposal(array $proposals, ServiceAdvisorField $field, ?int $concernId, ?int $lineId): ?array
    {
        foreach ($proposals as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((string) ($row['field'] ?? '') !== $field->value) {
                continue;
            }

            if ($field->isConcernNarrative()) {
                if ((int) ($row['concern_id'] ?? 0) === (int) $concernId) {
                    return $row;
                }
            } elseif ($field === ServiceAdvisorField::VisitReason) {
                return $row;
            } elseif ($field === ServiceAdvisorField::LineNote) {
                if ((int) ($row['line_id'] ?? 0) === (int) $lineId) {
                    return $row;
                }
            }
        }

        return null;
    }
}
