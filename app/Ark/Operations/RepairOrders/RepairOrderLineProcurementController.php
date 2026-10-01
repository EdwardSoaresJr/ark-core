<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class RepairOrderLineProcurementController
{
    use RecordsRepairOrderEstimateMutation;
    public function __invoke(Request $request, RepairOrder $repairOrder, RepairOrderLine $line, PartProcurementTransition $procurement, RepairOrderConcurrency $concurrency): RedirectResponse
    {
        abort_unless($line->repair_order_id === $repairOrder->id, 404);
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'procurement_state' => ['required', Rule::enum(PartProcurementState::class)],
        ]);

        $toState = PartProcurementState::from($data['procurement_state']);

        if ($toState === PartProcurementState::Canceled && ! $request->user()?->can(ArkCapability::ProcurementCancel->value)) {
            return redirect()
                ->back()
                ->with('error', 'Canceling a part order requires procurement authority.');
        }

        try {
            if (! $procurement->move($repairOrder, $line, $toState, $request->user())) {
                return redirect()
                    ->back()
                    ->with('status', 'Part state unchanged.');
            }
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() >= 500) {
                throw $exception;
            }

            return redirect()
                ->back()
                ->with('error', $exception->getMessage() ?: 'That part state move is not available.');
        }

        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('line-'.$line->id)
            ->with('status', 'Part marked '.$toState->label($line->part_source).'.');
    }
}
