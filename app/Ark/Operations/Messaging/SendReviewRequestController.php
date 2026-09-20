<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class SendReviewRequestController
{
    use RecordsRepairOrderEstimateMutation;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        SendReviewRequestDeliveryAction $send,
        RepairOrderLifecycleTransition $lifecycle,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        abort_unless(
            $request->user()?->can(ArkCapability::RepairOrdersManage->value)
                || $request->user()?->can(ArkCapability::RepairOrdersLifecycle->value),
            403,
        );

        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'delivery' => ['required', Rule::enum(OutboundDeliveryMode::class)],
            'email' => [
                'exclude_unless:delivery,email,both',
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
            ],
            'close_paid' => ['nullable', 'boolean'],
        ]);

        $mode = OutboundDeliveryMode::from($data['delivery']);

        try {
            $result = $send->execute(
                $repairOrder,
                $request->user(),
                $mode,
                $data['email'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['review_request' => $exception->getMessage()]);
        }

        $status = $result['status_label'];

        if ($request->boolean('close_paid') && ! $repairOrder->fresh()->isTerminal()) {
            $lifecycle->move(
                $repairOrder->fresh(),
                RepairOrderStatus::Closed,
                $request->user(),
                'paid',
            );
            $this->recordRepairOrderEstimateMutation($repairOrder->fresh(), $request->user());
            $status = $status.' RO closed as Paid.';
        } else {
            $this->recordRepairOrderEstimateMutation($repairOrder->fresh(), $request->user());
        }

        return redirect()
            ->back()
            ->with('status', $status);
    }
}
