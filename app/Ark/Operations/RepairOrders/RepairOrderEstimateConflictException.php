<?php

namespace App\Ark\Operations\RepairOrders;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RepairOrderEstimateConflictException extends Exception
{
    public function __construct(public readonly RepairOrder $repairOrder)
    {
        parent::__construct($this->conflictMessage());
    }

    public function conflictMessage(): string
    {
        $repairOrder = $this->repairOrder->loadMissing('estimateVersionActor:id,name');
        $actorName = $repairOrder->estimateVersionActor?->name;
        $changedAt = app(RepairOrderWorksheetPresence::class)->formatChangedAt($repairOrder->estimate_version_at);

        if ($actorName && $changedAt) {
            return "Estimate updated by {$actorName} at {$changedAt}. Refresh the worksheet before saving.";
        }

        if ($actorName) {
            return "Estimate updated by {$actorName}. Refresh the worksheet before saving.";
        }

        return 'This estimate changed while you were working. Refresh the worksheet before saving.';
    }

    public function render(Request $request): Response|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($this->payload(), 409);
        }

        return response()->view('errors.409', [
            'message' => $this->conflictMessage(),
        ], 409);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $repairOrder = $this->repairOrder->loadMissing('estimateVersionActor:id,name');

        return [
            'conflict' => true,
            'message' => $this->conflictMessage(),
            'estimate_version' => (int) $repairOrder->estimate_version,
            'changed_by' => $repairOrder->estimateVersionActor?->name,
            'changed_at' => $repairOrder->estimate_version_at?->toIso8601String(),
            'actor_id' => $repairOrder->estimate_version_actor_id,
        ];
    }
}
