<?php

namespace App\Ark\Operations\Work;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdvisorFollowUpStoreController
{
    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);

        AdvisorFollowUp::query()->create([
            ...$data,
            'created_by_user_id' => $request->user()->id,
            'due_at' => $data['due_at'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Follow-up added to Work.',
                'summary_label' => 'Added',
                'kind' => 'follow-up',
            ]);
        }

        return redirect()
            ->route('operations.index')
            ->with('status', 'Follow-up added to Work.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
            'due_at' => ['required', 'date'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'repair_order_shop_number' => ['nullable', 'integer', 'min:1'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
        ]);

        if (isset($data['repair_order_shop_number'])) {
            $repairOrder = RepairOrder::query()
                ->where('repair_order_id', $data['repair_order_shop_number'])
                ->firstOrFail();
            $data['repair_order_id'] = $repairOrder->id;
            unset($data['repair_order_shop_number']);
            $data['customer_id'] ??= $repairOrder->customer_id;
            $data['vehicle_id'] ??= $repairOrder->vehicle_id;
        } else {
            unset($data['repair_order_shop_number']);
        }

        return $data;
    }
}
