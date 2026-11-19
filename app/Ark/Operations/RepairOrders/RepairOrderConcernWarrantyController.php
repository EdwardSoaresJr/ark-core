<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\EstimatePricing\EffectiveWarrantyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RepairOrderConcernWarrantyController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        ChangeConcernWarrantyAction $action,
        EffectiveWarrantyResolver $resolver,
    ): JsonResponse|RedirectResponse {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);

        $data = $request->validate([
            'action' => ['required', 'string', 'in:override,inherit'],
            'warranty' => ['nullable', 'array'],
            'warranty.months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'warranty.miles' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'warranty.covers_parts' => ['nullable', 'boolean'],
            'warranty.covers_labor' => ['nullable', 'boolean'],
            'warranty.description' => ['nullable', 'string', 'max:2000'],
            'warranty.exclusions' => ['nullable', 'string', 'max:2000'],
            'warranty.third_party' => ['nullable', 'boolean'],
        ]);

        $warranty = $data['warranty'] ?? null;
        if (is_array($warranty)) {
            $warranty['covers_parts'] = $request->boolean('warranty.covers_parts');
            $warranty['covers_labor'] = $request->boolean('warranty.covers_labor');
            $warranty['third_party'] = $request->boolean('warranty.third_party');
        }

        $updated = $data['action'] === 'inherit'
            ? $action->inherit($concern, $request->user())
            : $action->override($concern, $warranty, $request->user());

        $status = $data['action'] === 'inherit'
            ? 'Warranty inheritance restored.'
            : 'Concern warranty override saved.';

        if ($request->expectsJson()) {
            return response()->json([
                'concern_id' => $updated->id,
                'inherits' => $updated->inheritsClassWarranty(),
                'effective' => $resolver->forConcern($updated->loadMissing('repairOrder')),
                'status' => $status,
            ]);
        }

        return back()->with('status', $status);
    }
}
