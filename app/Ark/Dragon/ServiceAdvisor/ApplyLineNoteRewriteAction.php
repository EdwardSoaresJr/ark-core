<?php

namespace App\Ark\Dragon\ServiceAdvisor;

use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Dragon\Assist\DragonAssistStatus;
use App\Ark\Dragon\Assist\DragonAssistTaskType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Models\User;
use RuntimeException;

final class ApplyLineNoteRewriteAction
{
    public function __construct(
        private readonly ApplyNarrativeFieldRewriteAction $applyField,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        RepairOrderLine $line,
        DragonAssistRequest $assist,
        User $actor,
        ?string $editedProposal = null,
        ?int $openedEstimateVersion = null,
    ): DragonServiceAdvisorApplication {
        abort_unless((int) $line->repair_order_id === (int) $repairOrder->id, 404);
        abort_unless((int) $assist->repair_order_id === (int) $repairOrder->id, 404);
        abort_unless($assist->task_type === DragonAssistTaskType::ServiceAdvisorRewrite, 422);
        abort_unless($assist->status === DragonAssistStatus::Completed, 422, 'Dragon proposal is not ready.');
        abort_unless($line->type->isNote(), 422, 'Only note lines can be rewritten.');

        $payload = $assist->payload_json ?? [];
        $field = ServiceAdvisorField::from((string) ($payload['selected_field'] ?? ''));
        abort_unless($field === ServiceAdvisorField::LineNote, 422);

        $payloadLineId = (int) (($payload['line']['id'] ?? 0));
        abort_unless($payloadLineId === (int) $line->id, 422, 'Assist does not match this note line.');

        $mode = ServiceAdvisorMode::from((string) ($payload['mode'] ?? ServiceAdvisorMode::ServiceAdvisorRewrite->value));
        $originalHash = (string) ($payload['original_hash'] ?? '');
        $originalText = (string) ($payload['selected_text'] ?? '');
        $proposal = (string) ($assist->result?->result_json['proposal'] ?? '');

        if ($proposal === '') {
            throw new RuntimeException('Dragon proposal is missing.');
        }

        return $this->applyField->execute(
            $repairOrder,
            $field,
            $originalText,
            $originalHash,
            $proposal,
            $actor,
            concern: $line->concern,
            line: $line,
            assist: $assist,
            editedProposal: $editedProposal,
            openedEstimateVersion: $openedEstimateVersion,
            mode: $mode,
        );
    }
}
