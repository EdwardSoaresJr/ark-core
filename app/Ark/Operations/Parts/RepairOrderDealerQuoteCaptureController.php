<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class RepairOrderDealerQuoteCaptureController
{
    use RecordsRepairOrderEstimateMutation;

    public function analyze(
        Request $request,
        RepairOrder $repairOrder,
        CaptureDealerQuoteAction $action,
    ): JsonResponse {
        $repairOrder->ensureOpenForEditing();

        $request->validate([
            'quote_pdf' => ['nullable', 'file', 'mimes:pdf,txt,png,jpg,jpeg,webp', 'max:10240'],
            'quote_text' => ['nullable', 'string', 'max:200000'],
        ]);

        if (! $request->hasFile('quote_pdf') && blank($request->input('quote_text'))) {
            return response()->json(['message' => 'Upload a PDF, quote photo, or paste the quote text.'], 422);
        }

        try {
            $result = $action->analyze(
                $repairOrder,
                $request->file('quote_pdf'),
                $request->input('quote_text'),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $repairOrder->load(['concerns.workGroups.lines', 'customer']);
        $settings = ShopSettings::current();
        $concerns = $repairOrder->concerns->sortBy('position')->values();
        $defaultConcern = $concerns->count() === 1 ? $concerns->first() : null;
        $defaultMatrix = $defaultConcern
            ? $defaultConcern->defaultPartsMatrix($settings)
            : $settings->defaultPartsMatrix();

        return response()->json([
            'supplier_name' => $result['supplier_name'],
            'quote_number' => $result['quote_number'],
            'vehicle_description' => $result['vehicle_description'],
            'vin' => $result['vin'],
            'dealer_total' => $result['dealer_total'],
            'dealer_total_cents' => $result['dealer_total_cents'],
            'raw_text' => $result['raw_text'],
            'original_filename' => $result['original_filename'],
            'temp_storage_path' => $result['temp_storage_path'],
            'default_parts_matrix_key' => $defaultMatrix['key'],
            'default_parts_matrix_name' => $defaultMatrix['name'],
            'default_repair_order_concern_id' => $defaultConcern?->id,
            'parts_matrices' => $settings->partsMatrices(),
            'lines' => $result['lines'],
            'concerns' => $concerns
                ->map(function (RepairOrderConcern $concern) use ($settings): array {
                    $matrix = $concern->defaultPartsMatrix($settings);

                    return [
                        'id' => $concern->id,
                        'summary' => $concern->summary,
                        'billing_posture' => $concern->billing_posture->value,
                        'default_parts_matrix_key' => $matrix['key'],
                        'default_parts_matrix_name' => $matrix['name'],
                        'work_groups' => $concern->workGroups
                            ->map(fn ($workGroup): array => [
                                'id' => $workGroup->id,
                                'title' => $workGroup->title,
                                'has_labor_anchor' => $workGroup->hasPartsAttachAnchor(),
                            ])
                            ->values()
                            ->all(),
                    ];
                })
                ->all(),
        ]);
    }

    public function store(
        Request $request,
        RepairOrder $repairOrder,
        CaptureDealerQuoteAction $action,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'capture' => ['required', 'array'],
            'capture.supplier_name' => ['nullable', 'string', 'max:191'],
            'capture.quote_number' => ['nullable', 'string', 'max:64'],
            'capture.vehicle_description' => ['nullable', 'string', 'max:191'],
            'capture.vin' => ['nullable', 'string', 'max:32'],
            'capture.dealer_total_cents' => ['nullable', 'integer', 'min:0'],
            'capture.raw_text' => ['required', 'string', 'max:200000'],
            'capture.original_filename' => ['nullable', 'string', 'max:255'],
            'capture.temp_storage_path' => ['nullable', 'string', 'max:512'],
            'capture.lines' => ['required', 'array', 'min:1'],
            'capture.lines.*.source_key' => ['required', 'string', 'max:64'],
            'capture.lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'capture.lines.*.part_number' => ['nullable', 'string', 'max:64'],
            'capture.lines.*.description' => ['required', 'string', 'max:255'],
            'capture.lines.*.part_cost' => ['required', 'numeric', 'min:0'],
            'capture.lines.*.unit_cost_cents' => ['nullable', 'integer', 'min:0'],
            'capture.lines.*.extended_cost_cents' => ['nullable', 'integer', 'min:0'],
            'assignments' => ['required', 'array'],
            'assignments.*.source_key' => ['required', 'string', 'max:64'],
            'assignments.*.repair_order_concern_id' => [
                'nullable',
                'integer',
                Rule::exists('repair_order_concerns', 'id')->where('repair_order_id', $repairOrder->id),
            ],
            'assignments.*.repair_order_work_group_id' => [
                'nullable',
                'integer',
                Rule::exists('repair_order_work_groups', 'id')->where(
                    fn ($query) => $query->whereIn(
                        'repair_order_concern_id',
                        $repairOrder->concerns()->select('id'),
                    ),
                ),
            ],
            'assignments.*.part_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'assignments.*.description' => ['nullable', 'string', 'max:255'],
            'assignments.*.pricing_matrix_key' => [
                'nullable',
                'string',
                'max:255',
                Rule::in(collect(ShopSettings::current()->partsMatrices())->pluck('key')->all()),
            ],
        ]);

        $assignments = collect($data['assignments'])
            ->filter(fn (array $row): bool => filled($row['repair_order_concern_id'] ?? null))
            ->values()
            ->all();

        try {
            $result = $action->import($repairOrder, $assignments, $data['capture'], $request->user());
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('operations.repair-orders.show', $repairOrder)
                ->withFragment('estimate-lines')
                ->with('error', $exception->getMessage());
        }

        $concernList = implode(', ', $result['concerns']);
        $workGroupIds = $result['work_group_ids'] ?? [];
        $fragment = count($workGroupIds) === 1
            ? 'repair-action-'.$workGroupIds[0]
            : 'estimate-lines';

        $this->recordRepairOrderEstimateMutation($repairOrder, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment($fragment)
            ->with('status', sprintf(
                'Added %d part line%s from dealer quote into %s.',
                $result['imported'],
                $result['imported'] === 1 ? '' : 's',
                $concernList !== '' ? $concernList : 'selected concerns',
            ));
    }
}
