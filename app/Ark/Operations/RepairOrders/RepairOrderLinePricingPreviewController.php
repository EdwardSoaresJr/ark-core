<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderLinePricingPreviewController
{
    public function __invoke(Request $request, RepairOrder $repairOrder, RepairOrderLinePricing $pricing): JsonResponse
    {
        $repairOrder->ensureOpenForEditing();

        $data = $request->validate([
            'type' => ['required', Rule::enum(RepairOrderLineType::class)],
            'part_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'pricing_mode' => ['nullable', Rule::in(['matrix', 'manual'])],
            'pricing_matrix_key' => ['nullable', 'string', 'max:255'],
            'pricing_matrix_explicit' => ['nullable', 'boolean'],
            'unit_price_override' => ['nullable', 'boolean'],
            'repair_order_concern_id' => [
                'nullable',
                'integer',
                Rule::exists('repair_order_concerns', 'id')->where('repair_order_id', $repairOrder->id),
            ],
        ]);

        if ($data['type'] === RepairOrderLineType::Sublet->value) {
            return response()->json($pricing->subletPreviewFor($data));
        }

        if ($data['type'] !== RepairOrderLineType::Part->value) {
            return response()->json([
                'guidance' => 'Pricing guidance applies to part and sublet lines.',
                'posture' => 'not applicable',
                'suggested_sell' => null,
                'current_sell' => null,
                'margin_percentage' => null,
                'matrix_margin_percentage' => null,
                'markup_percentage' => null,
            ]);
        }

        return response()->json($pricing->previewFor($data, $repairOrder));
    }
}
