<?php

namespace App\Ark\Tech\Http;

use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Tech\TechStaffGate;
use App\Ark\Tech\TechVoiceProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class TechVoiceUtteranceController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItem $item,
        TechStaffGate $gate,
        TechVoiceProposalService $proposals,
    ): JsonResponse {
        abort_unless($gate->canRecordFinding($request->user(), $repairOrder), 403);

        $request->validate([
            'audio' => ['nullable', 'file', 'max:2048'],
            'transcript' => ['nullable', 'string', 'max:8000'],
        ]);

        $wav = null;
        if ($request->hasFile('audio')) {
            $wav = (string) file_get_contents($request->file('audio')->getRealPath());
        }

        try {
            $proposal = $proposals->proposeFromAudioOrTranscript(
                $wav,
                $request->input('transcript'),
                $item->id,
                $repairOrder->id,
                (int) $request->user()->id,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'dragon' => false,
                'manual_ok' => true,
            ], 503);
        }

        return response()->json([
            'proposal' => $proposal,
            'written' => false,
            'confirm_required' => true,
        ]);
    }
}
