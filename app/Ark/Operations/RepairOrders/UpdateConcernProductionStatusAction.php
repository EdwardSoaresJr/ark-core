<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Labor\RecognizeConcernFlagProductionAction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class UpdateConcernProductionStatusAction
{
    public function __construct(
        private readonly WorkCompletionAuthorization $authorization,
        private readonly OperationalEventRecorder $events,
        private readonly RecognizeConcernFlagProductionAction $recognizeFlagProduction,
    ) {}

    /**
     * @param  array<string, mixed>  $extraEventPayload
     * @return array{recognition: array<string, mixed>}
     */
    public function execute(
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        ScopeProductionStatus $newStatus,
        ?User $actor,
        array $extraEventPayload = [],
    ): array {
        if ((int) $concern->repair_order_id !== (int) $repairOrder->id) {
            abort(404);
        }

        if (! $concern->tracksProduction()) {
            throw ValidationException::withMessages([
                'production_status' => 'Production status does not apply to deferred or declined scopes.',
            ]);
        }

        if ($newStatus === ScopeProductionStatus::Completed) {
            $reason = $this->authorization->completionBlockedReason($concern);

            if ($reason !== null) {
                throw ValidationException::withMessages([
                    'production_status' => $reason,
                ]);
            }
        }

        $priorStatus = $concern->productionStatus()->value;

        $concern->update([
            'production_status' => $newStatus,
        ]);
        $concern->refresh();

        $sourceEvent = $this->events->record(
            OperationalEventName::ConcernProductionStatusChanged,
            $repairOrder,
            actor: $actor,
            payload: [
                'concern_id' => $concern->id,
                'prior_production_status' => $priorStatus,
                'new_production_status' => $concern->productionStatus()->value,
                ...$extraEventPayload,
            ],
        );

        $recognition = $this->recognizeFlagProduction->handle(
            $repairOrder->fresh(['assignedTechnician']),
            $concern->fresh(['lines']),
            ScopeProductionStatus::fromStored($priorStatus),
            $concern->productionStatus(),
            $sourceEvent,
            $actor,
        );

        return ['recognition' => $recognition];
    }
}
