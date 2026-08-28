<?php

namespace App\Ark\Tech\Http;

use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionChecklistStatus;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\UpdateInspectionChecklistItemAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Tech\TechStaffGate;
use App\Ark\Tech\TechVoiceProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class TechVoiceConfirmController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItem $item,
        TechStaffGate $gate,
        TechVoiceProposalService $proposals,
        EnsureInspectionAction $ensureInspection,
        UpdateInspectionChecklistItemAction $updateItem,
    ): JsonResponse {
        abort_unless($gate->canRecordFinding($request->user(), $repairOrder), 403);

        $data = $request->validate([
            'proposal_id' => ['required', 'uuid'],
        ]);

        $proposal = $proposals->pull($data['proposal_id']);
        if ($proposal === null || ($proposal['written'] ?? false) === true) {
            return response()->json(['message' => 'Proposal expired or already confirmed.'], 422);
        }

        if ((int) $proposal['inspection_item_id'] !== (int) $item->id
            || (int) $proposal['user_id'] !== (int) $request->user()->id) {
            return response()->json(['message' => 'Proposal does not match this inspection item.'], 422);
        }

        $repairOrder->ensureOpenForEditing();
        $inspection = $ensureInspection->execute($repairOrder, $request->user());

        $note = trim(implode(' ', array_filter([
            (string) ($proposal['finding'] ?? ''),
            filled($proposal['rotor_condition'] ?? null) ? 'Rotors: '.$proposal['rotor_condition'] : null,
        ])));

        $measurements = [];
        foreach ($proposal['measurements'] ?? [] as $row) {
            $name = (string) ($row['name'] ?? '');
            $measurements[] = [
                'key' => $name,
                'name' => $name,
                'value' => (string) ($row['value'] ?? ''),
                'unit' => $row['unit'] ?? 'mm',
            ];
        }

        $updateItem->execute(
            $repairOrder,
            $inspection,
            $item,
            InspectionChecklistStatus::NeedsAttention,
            $request->user(),
            $note !== '' ? $note : null,
            null,
            null,
            null,
            $measurements,
        );

        $proposals->markWritten($data['proposal_id']);

        Log::info('tech.voice.confirmed', [
            'user_id' => $request->user()->id,
            'repair_order_id' => $repairOrder->id,
            'item_id' => $item->id,
            'source' => 'voice_confirmed',
            'proposal_id' => $data['proposal_id'],
        ]);

        return response()->json([
            'saved' => true,
            'source' => 'voice_confirmed',
        ]);
    }
}
