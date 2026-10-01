<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderLifecycleController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(Request $request, RepairOrder $repairOrder, RepairOrderLifecycleTransition $lifecycle, RepairOrderConcurrency $concurrency): RedirectResponse
    {
        $concurrency->guard($request, $repairOrder);

        $requestedStatus = (string) $request->input('status');

        if ($requestedStatus === '') {
            return redirect()
                ->back()
                ->withErrors(['lifecycle' => 'Choose a lifecycle move before updating this repair order.']);
        }

        $closeVariantKey = null;

        if (str_contains($requestedStatus, ':')) {
            [$requestedStatus, $closeVariantKey] = explode(':', $requestedStatus, 2);
        }

        $toStatusSlug = RepairOrderWorkflowStatus::normalizeSlug($requestedStatus);

        if (($reason = $lifecycle->blockingReason($repairOrder, $toStatusSlug, $request->user(), $closeVariantKey)) !== null) {
            return redirect()
                ->back()
                ->withErrors(['lifecycle' => $reason]);
        }

        $lostReason = null;
        $lostReasonNote = null;

        if ($toStatusSlug === RepairOrderStatus::Closed->value && $closeVariantKey === 'lost') {
            $validated = $request->validate([
                'lost_reason_key' => ['required', Rule::enum(RepairOrderLostReason::class)],
                'lost_reason_note' => ['nullable', 'string', 'max:500'],
            ], [
                'lost_reason_key.required' => 'Choose why this repair order closed lost before continuing.',
            ]);

            $lostReason = RepairOrderLostReason::from($validated['lost_reason_key']);
            $lostReasonNote = isset($validated['lost_reason_note']) ? trim((string) $validated['lost_reason_note']) : null;

            if ($lostReason->requiresNote() && ($lostReasonNote === null || $lostReasonNote === '')) {
                return redirect()
                    ->back()
                    ->withErrors(['lifecycle' => 'Add a short note when the lost reason is Other.']);
            }
        }

        $reviewRequestSent = null;
        $reviewNotRequestedReason = null;

        // Paid close no longer requires advisor bookkeeping ("did we ask?").
        // Review requests are a separate send action when the advisor chooses to send.

        $lifecycle->move(
            $repairOrder,
            $toStatusSlug,
            $request->user(),
            $closeVariantKey,
            $lostReason,
            $lostReasonNote,
            $reviewRequestSent,
            $reviewNotRequestedReason,
        );

        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        $repairOrder = $repairOrder->fresh();

        if ($toStatusSlug === RepairOrderStatus::ReadyPickup->value) {
            $hasInvoice = app(BalanceDueCalculator::class)->issuedInvoice($repairOrder) !== null;
            $finalizingLabel = $repairOrder->statusDisplayLabel();

            return redirect()
                ->to(route('operations.repair-orders.show', $repairOrder).'#financial-rail')
                ->with(
                    'status',
                    $hasInvoice
                        ? 'RO moved to '.$finalizingLabel.'. Final invoice issued.'
                        : 'RO moved to '.$finalizingLabel.'. Review Financial to issue the final invoice.',
                );
        }

        if ($toStatusSlug === RepairOrderStatus::Closed->value) {
            return redirect()
                ->back()
                ->with('status', 'RO closed as '.$repairOrder->statusDisplayLabel().'.');
        }

        return redirect()
            ->back()
            ->with('status', 'RO moved to '.$repairOrder->statusDisplayLabel().'.');
    }
}
