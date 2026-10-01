<?php

namespace App\Ark\Operations\Workboard;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationsWorkboardTriageFragmentController
{
    public function __invoke(
        Request $request,
        WorkboardTriageRepairOrderQuery $repairOrderQuery,
        WorkboardTriageProjection $triageProjection,
    ): JsonResponse {
        $selectedQueue = WorkboardQueueCatalog::resolveExplicitQueue(
            $request->query('queue'),
            $request->query('focus'),
        );

        $workboardWorkspace = $triageProjection->forAdvisorWorkspace(
            $repairOrderQuery->forAdvisor(),
            $selectedQueue,
            applyDefaultQueue: $selectedQueue === null,
        );

        return response()->json([
            'queue_count' => $workboardWorkspace->queueCount,
            'signature' => $this->signature($workboardWorkspace),
            'html' => view('operations.workboard.partials.workspace-live-body', [
                'workboardWorkspace' => $workboardWorkspace,
            ])->render(),
        ]);
    }

    private function signature(WorkboardQueueWorkspaceProjection $workspace): string
    {
        $parts = [
            (string) $workspace->queueCount,
            $workspace->selectedQueueKey ?? '',
            (string) $workspace->selectedQueueCount,
        ];

        foreach ($workspace->navGroups as $group) {
            foreach ($group->items as $item) {
                $parts[] = $item->key.':'.$item->count;
            }
        }

        foreach ($workspace->visibleCards as $card) {
            $parts[] = implode(':', [
                (string) $card->repairOrder->id,
                $card->signalLabel ?? '',
                $card->ageLabel,
                (string) (int) $card->countsAsNeedsAttention,
                (string) (int) $card->countsAsOverduePickup,
            ]);
        }

        if ($workspace->pickupOverflow !== null) {
            $parts[] = implode(':', [
                (string) $workspace->pickupOverflow->totalAwaitingPickup,
                (string) $workspace->pickupOverflow->staleCount,
            ]);
        }

        return md5(implode('|', $parts));
    }
}
