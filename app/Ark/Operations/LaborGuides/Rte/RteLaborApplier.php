<?php

namespace App\Ark\Operations\LaborGuides\Rte;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineLaborInput;
use App\Ark\Operations\RepairOrders\RepairOrderLinePricing;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RteLaborApplier
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly RteLaborLookup $lookup,
        private readonly RepairOrderLinePricing $pricing,
        private readonly EstimateTotalsCalculator $calculator,
        private readonly EstimateDocumentService $documents,
        private readonly OperationalEventRecorder $events,
        private readonly RteLaborObservationRecorder $observations,
        private readonly RepairOrderLifecycleTransition $lifecycle,
    ) {}

    /**
     * @param  array{
     *     repair_order_concern_id: int,
     *     repair_order_work_group_id?: int|null,
     *     lab_id: string,
     *     car_id_code: string,
     *     model_year?: int|null,
     *     hours_basis?: string,
     *     include_add_ons?: bool,
     *     apply_vehicle_age_padding?: bool,
     *     eng_id_code?: string|null,
     *     optional_diagnostic_lab_ids?: list<string>,
     * }  $data
     */
    public function apply(RepairOrder $repairOrder, User $user, array $data): RteLaborApplyResult
    {
        $repairOrder->ensureOpenForEditing();

        $basis = RteLaborHoursBasis::tryFrom($data['hours_basis'] ?? '') ?? RteLaborHoursBasis::default();
        $includeAddOns = array_key_exists('include_add_ons', $data)
            ? filter_var($data['include_add_ons'], FILTER_VALIDATE_BOOLEAN)
            : true;
        $applyVehicleAgePadding = array_key_exists('apply_vehicle_age_padding', $data)
            ? filter_var($data['apply_vehicle_age_padding'], FILTER_VALIDATE_BOOLEAN)
            : true;
        $modelYear = isset($data['model_year']) ? (int) $data['model_year'] : null;

        $laborRow = $this->lookup->laborByLabId(
            labId: $data['lab_id'],
            carIdCode: $data['car_id_code'],
            modelYear: $modelYear,
        );

        if ($laborRow === null) {
            throw ValidationException::withMessages([
                'lab_id' => 'That '.RepairTimeEngine::NAME.' labor row is not available for the selected vehicle.',
            ]);
        }

        $laborRow = $this->lookup->applyVehicleAgePaddingToRow($laborRow, $modelYear, $applyVehicleAgePadding);

        $baseHours = $this->lookup->hoursForBasis($laborRow, $basis);

        if ($baseHours === null || $baseHours <= 0) {
            throw ValidationException::withMessages([
                'hours_basis' => 'Selected '.RepairTimeEngine::NAME.' hours are not available for this job.',
            ]);
        }

        $mainDescription = filled($laborRow['job_desc'] ?? null)
            ? Str::limit(trim((string) $laborRow['job_desc']), 255, '')
            : RepairTimeEngine::NAME.' labor · '.$data['lab_id'];

        $lines = [
            $this->createLaborLine(
                repairOrder: $repairOrder,
                user: $user,
                data: $data,
                description: $mainDescription,
                hours: $baseHours,
                labId: $data['lab_id'],
                basis: $basis,
                isPrimary: true,
            ),
        ];

        if ($includeAddOns) {
            foreach ($laborRow['included_add_ons'] ?? [] as $included) {
                $addOnHours = $included[$basis->value.'_hr'] ?? $included['avg_hr'] ?? null;

                if ($addOnHours === null || (float) $addOnHours <= 0) {
                    continue;
                }

                $includedLabId = filled($included['lab_id'] ?? null)
                    ? (string) $included['lab_id']
                    : $data['lab_id'];

                $lines[] = $this->createLaborLine(
                    repairOrder: $repairOrder,
                    user: $user,
                    data: $data,
                    description: Str::limit((string) $included['description'], 255, ''),
                    hours: (float) $addOnHours,
                    labId: $includedLabId,
                    basis: $basis,
                    isPrimary: false,
                    addId: $included['add_id'] ?? null,
                    kind: (string) ($included['kind'] ?? 'bundled_add_on'),
                );
            }
        }

        foreach ($this->selectedOptionalDiagnosticOperations($laborRow, $data['optional_diagnostic_lab_ids'] ?? []) as $optional) {
            $lines[] = $this->createOptionalDiagnosticLine(
                repairOrder: $repairOrder,
                user: $user,
                data: $data,
                optional: $optional,
                fallbackLabId: $data['lab_id'],
                basis: $basis,
            );
        }

        $this->calculator->recalculateRepairOrder($repairOrder);

        if ($repairOrder->status->is(RepairOrderStatus::Draft)) {
            $this->lifecycle->move($repairOrder, RepairOrderStatus::Estimate, $user);
        }

        $this->documents->markDirtyForRepairOrder($repairOrder);
        $this->recordRepairOrderEstimateMutation($repairOrder, $user);

        $result = new RteLaborApplyResult($lines);

        $this->observations->recordRecommendationApplied(
            repairOrder: $repairOrder,
            user: $user,
            context: $data,
            result: $result,
            primaryLaborRow: $laborRow,
            packageApplied: false,
        );

        return $result;
    }

    /**
     * @param  array{
     *     repair_order_concern_id: int,
     *     repair_order_work_group_id?: int|null,
     *     lab_id: string,
     *     car_id_code: string,
     *     model_year?: int|null,
     *     hours_basis?: string,
     *     search_term: string,
     *     apply_vehicle_age_padding?: bool,
     *     eng_id_code?: string|null,
     *     optional_diagnostic_lab_ids?: list<string>,
     * }  $data
     */
    public function applySuggested(RepairOrder $repairOrder, User $user, array $data): RteLaborApplyResult
    {
        $repairOrder->ensureOpenForEditing();
        $repairOrder->loadMissing('vehicle');

        $basis = RteLaborHoursBasis::tryFrom($data['hours_basis'] ?? '') ?? RteLaborHoursBasis::default();
        $applyVehicleAgePadding = array_key_exists('apply_vehicle_age_padding', $data)
            ? filter_var($data['apply_vehicle_age_padding'], FILTER_VALIDATE_BOOLEAN)
            : true;
        $modelYear = isset($data['model_year']) ? (int) $data['model_year'] : null;

        $results = $this->lookup->searchWithRecommendation(
            carIdCode: $data['car_id_code'],
            modelYear: $modelYear,
            term: $data['search_term'],
            vehicle: $repairOrder->vehicle,
            repairOrder: $repairOrder,
            selectedEngIdCode: filled($data['eng_id_code'] ?? null) ? (string) $data['eng_id_code'] : null,
        );

        $package = $results['suggested_labor'] ?? null;

        if ($package === null) {
            throw ValidationException::withMessages([
                'search_term' => 'No suggested '.RepairTimeEngine::NAME.' package is available for that search.',
            ]);
        }

        if ((string) ($package['primary_lab_id'] ?? '') !== (string) $data['lab_id']) {
            throw ValidationException::withMessages([
                'lab_id' => 'Suggested '.RepairTimeEngine::NAME.' package is out of date. Search again and re-apply.',
            ]);
        }

        $package = $this->lookup->applyVehicleAgePaddingToSuggestedPackage(
            $package,
            $modelYear,
            $applyVehicleAgePadding,
        );

        $lines = [];

        foreach ($package['lines'] as $index => $lineSpec) {
            $hours = $lineSpec[$basis->value.'_hr'] ?? $lineSpec['avg_hr'] ?? null;

            if ($hours === null || (float) $hours <= 0) {
                continue;
            }

            $lines[] = $this->createLaborLine(
                repairOrder: $repairOrder,
                user: $user,
                data: $data,
                description: Str::limit((string) $lineSpec['description'], 255, ''),
                hours: (float) $hours,
                labId: (string) ($lineSpec['lab_id'] ?? $data['lab_id']),
                basis: $basis,
                isPrimary: $index === 0,
                addId: $lineSpec['add_id'] ?? null,
                kind: (string) ($lineSpec['kind'] ?? 'related_operation'),
            );
        }

        foreach ($this->selectedOptionalDiagnosticOperations(
            ['optional_diagnostic_operations' => $package['optional_diagnostic_operations'] ?? []],
            $data['optional_diagnostic_lab_ids'] ?? [],
        ) as $optional) {
            $optionalHours = $optional[$basis->value.'_hr'] ?? $optional['avg_hr'] ?? null;

            if ($optionalHours === null || (float) $optionalHours <= 0) {
                continue;
            }

            $lines[] = $this->createOptionalDiagnosticLine(
                repairOrder: $repairOrder,
                user: $user,
                data: $data,
                optional: $optional,
                fallbackLabId: (string) ($optional['lab_id'] ?? $data['lab_id']),
                basis: $basis,
            );
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'hours_basis' => 'Selected '.RepairTimeEngine::NAME.' hours are not available for the suggested package.',
            ]);
        }

        $this->calculator->recalculateRepairOrder($repairOrder);

        if ($repairOrder->status->is(RepairOrderStatus::Draft)) {
            $this->lifecycle->move($repairOrder, RepairOrderStatus::Estimate, $user);
        }

        $this->documents->markDirtyForRepairOrder($repairOrder);
        $this->recordRepairOrderEstimateMutation($repairOrder, $user);

        $result = new RteLaborApplyResult($lines);
        $primaryPackageLine = $package['lines'][0] ?? null;

        $this->observations->recordRecommendationApplied(
            repairOrder: $repairOrder,
            user: $user,
            context: $data,
            result: $result,
            primaryLaborRow: is_array($primaryPackageLine) ? $primaryPackageLine : null,
            packageApplied: true,
        );

        return $result;
    }

    /**
     * @param  array{
     *     repair_order_concern_id: int,
     *     repair_order_work_group_id?: int|null,
     *     lab_id: string,
     *     car_id_code: string,
     * }  $data
     */
    private function createLaborLine(
        RepairOrder $repairOrder,
        User $user,
        array $data,
        string $description,
        float $hours,
        string $labId,
        RteLaborHoursBasis $basis,
        bool $isPrimary,
        ?string $addId = null,
        string $kind = 'bundled_add_on',
    ): RepairOrderLine {
        $linePayload = [
            'repair_order_concern_id' => $data['repair_order_concern_id'],
            'repair_order_work_group_id' => $data['repair_order_work_group_id'] ?? null,
            'type' => RepairOrderLineType::Labor->value,
            'description' => $description,
            'quantity' => number_format($hours, 2, '.', ''),
            'labor_entered_hours' => number_format($hours, 2, '.', ''),
        ];

        $linePayload = RepairOrderLineType::Labor->applyInputDefaults($linePayload);
        $pricingAttributes = $this->pricing->attributesFor($linePayload, $repairOrder);
        $quantity = $pricingAttributes['quantity'] ?? $linePayload['quantity'];

        $line = $repairOrder->lines()->create([
            'repair_order_concern_id' => $linePayload['repair_order_concern_id'],
            'repair_order_work_group_id' => $linePayload['repair_order_work_group_id'],
            'type' => RepairOrderLineType::Labor,
            'description' => $linePayload['description'],
            'customer_description' => null,
            'quantity' => $quantity,
            'unit_price_cents' => $pricingAttributes['unit_price_cents'],
            'part_cost_cents' => null,
            'matrix_suggested_price_cents' => null,
            'pricing_mode' => null,
            'pricing_matrix_key' => null,
            'pricing_matrix_name' => null,
            'matrix_applied' => false,
            'vendor_name' => null,
            'part_number' => null,
            'sourcing_notes' => null,
            'has_core' => false,
            'save_old_part' => false,
            'is_private' => false,
            'is_overridden' => $pricingAttributes['is_overridden'],
            ...RepairOrderLineLaborInput::persistenceAttributes($pricingAttributes),
            'subtotal_cents' => $this->calculator->lineTotalCents($quantity, $pricingAttributes['unit_price_cents']),
        ]);

        $line->refresh();

        $this->events->record(
            OperationalEventName::EstimateLineAdded,
            $repairOrder,
            actor: $user,
            payload: [
                'line_id' => $line->id,
                'concern_id' => $line->repair_order_concern_id,
                'type' => $line->type->value,
                'subtotal_cents' => $line->subtotal_cents,
                'total_cents' => $line->total_cents,
                'source' => 'rte_labor_guide',
                'lab_id' => $labId,
                'hours_basis' => $basis->value,
                'rte_included_add_on' => ! $isPrimary,
                'rte_add_id' => $addId,
                'rte_included_kind' => $kind,
            ],
        );

        return $line;
    }

    /**
     * @param  array<string, mixed>  $laborRow
     * @param  list<string>  $selectedLabIds
     * @return list<array<string, mixed>>
     */
    private function selectedOptionalDiagnosticOperations(array $laborRow, array $selectedLabIds): array
    {
        $selectedLabIds = array_values(array_filter(array_map(
            fn (mixed $labId): string => trim((string) $labId),
            $selectedLabIds,
        )));

        if ($selectedLabIds === []) {
            return [];
        }

        return array_values(array_filter(
            $laborRow['optional_diagnostic_operations'] ?? [],
            fn (array $optional): bool => in_array((string) ($optional['lab_id'] ?? ''), $selectedLabIds, true),
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $optional
     */
    private function createOptionalDiagnosticLine(
        RepairOrder $repairOrder,
        User $user,
        array $data,
        array $optional,
        string $fallbackLabId,
        RteLaborHoursBasis $basis,
    ): RepairOrderLine {
        $hours = (float) ($optional[$basis->value.'_hr'] ?? $optional['avg_hr'] ?? 0);

        return $this->createLaborLine(
            repairOrder: $repairOrder,
            user: $user,
            data: $data,
            description: Str::limit((string) $optional['description'], 255, ''),
            hours: $hours,
            labId: filled($optional['lab_id'] ?? null) ? (string) $optional['lab_id'] : $fallbackLabId,
            basis: $basis,
            isPrimary: false,
            addId: $optional['add_id'] ?? null,
            kind: 'optional_diagnostic_operation',
        );
    }
}
