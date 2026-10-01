<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RepairOrderAuthorizationExceptionController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        RepairOrderConcurrency $concurrency,
        RecordAuthorizationExceptionAction $recordException,
    ): RedirectResponse {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        try {
            $data = $request->validate([
                'reason' => ['required', Rule::enum(AuthorizationExceptionReason::class)],
                'note' => ['required', 'string', 'max:2000'],
                'line_ids' => ['required', 'array', 'min:1'],
                'line_ids.*' => ['integer'],
            ], [
                'reason.required' => 'Select a basis.',
                'reason.enum' => 'Select a basis.',
                'note.required' => 'Describe which work this exception covers and why this basis applies.',
                'note.max' => 'The explanation must be 2000 characters or fewer.',
                'line_ids.required' => 'Select the labor or parts this exception covers.',
                'line_ids.min' => 'Select the labor or parts this exception covers.',
            ]);

            $recordException->execute(
                $repairOrder,
                $concern,
                $data['line_ids'],
                AuthorizationExceptionReason::from($data['reason']),
                $data['note'],
                $request->user(),
            );
        } catch (ValidationException $exception) {
            return $this->backToConcern($repairOrder, $concern)
                ->withInput()
                ->withErrors($exception->errors());
        }

        return $this->backToConcern($repairOrder, $concern)
            ->with('status', 'Exception recorded for the selected lines. This does not approve the work.');
    }

    private function backToConcern(RepairOrder $repairOrder, RepairOrderConcern $concern): RedirectResponse
    {
        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('concern-'.$concern->id);
    }
}
