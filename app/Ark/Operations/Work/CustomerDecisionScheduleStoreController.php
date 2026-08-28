<?php

namespace App\Ark\Operations\Work;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CustomerDecisionScheduleStoreController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'repair_order_shop_number' => ['required', 'integer', 'min:1'],
            'scheduled_for' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
            'schedule_customer' => ['nullable', 'boolean'],
        ]);

        $repairOrder = RepairOrder::query()
            ->where('repair_order_id', $data['repair_order_shop_number'])
            ->firstOrFail();

        $scheduledFor = Carbon::parse($data['scheduled_for'])->startOfDay();
        $scheduleCustomer = (bool) ($data['schedule_customer'] ?? false);

        if ($scheduleCustomer && $repairOrder->customer_id !== null) {
            $this->clearActiveSchedules(customerId: $repairOrder->customer_id);

            CustomerDecisionSchedule::query()->create([
                'created_by_user_id' => $request->user()->id,
                'customer_id' => $repairOrder->customer_id,
                'repair_order_id' => null,
                'scheduled_for' => $scheduledFor,
                'notes' => $data['notes'] ?? null,
            ]);
        } else {
            $this->clearActiveSchedules(repairOrderId: $repairOrder->id);

            CustomerDecisionSchedule::query()->create([
                'created_by_user_id' => $request->user()->id,
                'customer_id' => $repairOrder->customer_id,
                'repair_order_id' => $repairOrder->id,
                'scheduled_for' => $scheduledFor,
                'notes' => $data['notes'] ?? null,
            ]);
        }

        return redirect()
            ->route('operations.index')
            ->with('status', 'Decision scheduled — returns to Work the day before.');
    }

    private function clearActiveSchedules(?int $repairOrderId = null, ?int $customerId = null): void
    {
        $query = CustomerDecisionSchedule::query()->whereNull('cleared_at');

        if ($repairOrderId !== null) {
            $query->where('repair_order_id', $repairOrderId);
        }

        if ($customerId !== null) {
            $query->where('customer_id', $customerId)->whereNull('repair_order_id');
        }

        $query->update(['cleared_at' => now()]);
    }
}
