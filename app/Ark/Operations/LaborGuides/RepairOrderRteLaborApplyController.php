<?php

namespace App\Ark\Operations\LaborGuides;

use App\Ark\Operations\LaborGuides\Rte\RepairTimeEngine;
use App\Ark\Operations\LaborGuides\Rte\RteLaborApplier;
use App\Ark\Operations\LaborGuides\Rte\RteLaborGuideContext;
use App\Ark\Operations\LaborGuides\Rte\RteLaborHoursBasis;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderRteLaborApplyController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RteLaborGuideContext $context,
        RteLaborApplier $applier,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $guideContext = $context->forRepairOrder($repairOrder);

        if (! $guideContext['available']) {
            return back()->with('error', $guideContext['blocked_reason']);
        }

        $validated = $request->validate([
            'repair_order_concern_id' => [
                'required',
                Rule::exists('repair_order_concerns', 'id')->where('repair_order_id', $repairOrder->id),
            ],
            'repair_order_work_group_id' => [
                'nullable',
                Rule::exists('repair_order_work_groups', 'id')->where('repair_order_id', $repairOrder->id),
            ],
            'lab_id' => ['required', 'string', 'max:14'],
            'car_id_code' => ['required', 'string', 'max:7'],
            'hours_basis' => ['required', Rule::enum(RteLaborHoursBasis::class)],
            'include_add_ons' => ['nullable', 'boolean'],
            'apply_vehicle_age_padding' => ['nullable', 'boolean'],
            'apply_suggested' => ['nullable', 'boolean'],
            'search_term' => ['nullable', 'string', 'max:120'],
            'eng_id_code' => ['nullable', 'string', 'max:12'],
            'optional_diagnostic_lab_ids' => ['nullable', 'array'],
            'optional_diagnostic_lab_ids.*' => ['string', 'max:14'],
        ]);

        if (! collect($guideContext['car_candidates'])->contains('car_id_code', $validated['car_id_code'])) {
            return back()->with('error', 'Selected '.RepairTimeEngine::NAME.' vehicle code is not valid for this repair order.');
        }

        $payload = [
            ...$validated,
            'model_year' => $guideContext['model_year'],
            'apply_vehicle_age_padding' => $request->boolean('apply_vehicle_age_padding', true),
            'optional_diagnostic_lab_ids' => array_values(array_filter(
                $validated['optional_diagnostic_lab_ids'] ?? [],
                fn (mixed $labId): bool => filled($labId),
            )),
        ];

        if ($request->boolean('apply_suggested')) {
            if (! filled($validated['search_term'] ?? null)) {
                return back()->with('error', 'Search again before applying suggested '.RepairTimeEngine::NAME.' labor.');
            }

            $result = $applier->applySuggested($repairOrder, $request->user(), [
                ...$payload,
                'search_term' => (string) $validated['search_term'],
            ]);
        } else {
            $result = $applier->apply($repairOrder, $request->user(), [
                ...$payload,
                'include_add_ons' => $request->boolean('include_add_ons', true),
            ]);
        }

        $fragment = filled($validated['repair_order_work_group_id'] ?? null)
            ? 'repair-action-'.$validated['repair_order_work_group_id']
            : 'estimate-lines';

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment($fragment)
            ->with('status', $result->statusMessage());
    }
}
