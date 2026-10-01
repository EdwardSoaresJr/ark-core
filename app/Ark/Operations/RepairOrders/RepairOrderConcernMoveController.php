<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\Documents\EstimateDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RepairOrderConcernMoveController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(Request $request, RepairOrder $repairOrder, RepairOrderConcern $concern, EstimateDocumentService $documents, RepairOrderConcurrency $concurrency): RedirectResponse
    {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        DB::transaction(function () use ($repairOrder, $concern, $data, $documents): void {
            $ordered = RepairOrderConcern::query()
                ->where('repair_order_id', $repairOrder->id)
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->values();

            $index = $ordered->search(fn (RepairOrderConcern $row): bool => $row->id === $concern->id);

            if ($index === false) {
                return;
            }

            $swapIndex = $data['direction'] === 'up' ? $index - 1 : $index + 1;

            if ($swapIndex < 0 || $swapIndex >= $ordered->count()) {
                return;
            }

            $rows = $ordered->all();
            [$rows[$index], $rows[$swapIndex]] = [$rows[$swapIndex], $rows[$index]];

            foreach (array_values($rows) as $position => $row) {
                $nextPosition = $position + 1;

                if ((int) $row->position !== $nextPosition) {
                    $row->update(['position' => $nextPosition]);
                }
            }

            $documents->markDirtyForRepairOrder($repairOrder);
        });

        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('estimate-lines')
            ->with('status', 'Concern order updated.');
    }
}
