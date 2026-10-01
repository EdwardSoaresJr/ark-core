<?php

namespace App\Ark\Dragon\ServiceAdvisor;

use App\Ark\Dragon\Assist\DragonAssistRequest;
use App\Ark\Dragon\Assist\DragonAssistStatus;
use App\Ark\Dragon\Assist\DragonAssistTaskType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use RuntimeException;

final class ApplyVisitReasonRewriteAction
{
    public function __construct(
        private readonly ApplyNarrativeFieldRewriteAction $applyField,
    ) {}

    public function execute(
        RepairOrder $repairOrder,
        DragonAssistRequest $assist,
        User $actor,
        ?string $editedProposal = null,
        ?int $openedEstimateVersion = null,
    ): DragonServiceAdvisorApplication {
        abort_unless((int) $assist->repair_order_id === (int) $repairOrder->id, 404);
        abort_unless($assist->task_type === DragonAssistTaskType::ServiceAdvisorRewrite, 422);
        abort_unless($assist->status === DragonAssistStatus::Completed, 422, 'Dragon proposal is not ready.');

        $payload = $assist->payload_json ?? [];
        $field = ServiceAdvisorField::from((string) ($payload['selected_field'] ?? ''));
        abort_unless($field === ServiceAdvisorField::VisitReason, 422);

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
            assist: $assist,
            editedProposal: $editedProposal,
            openedEstimateVersion: $openedEstimateVersion,
            mode: $mode,
        );
    }
}
