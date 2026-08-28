<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\RepairOrders\ApprovalForecastProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderFreeText;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Models\User;
use Illuminate\Support\Collection;

final class EstimateDocumentPdfSnapshot
{
    public function __construct(
        private readonly EstimateSnapshotBuilder $snapshotBuilder,
        private readonly InvoicePdfFinancialSnapshot $invoiceFinancialSnapshot,
        private readonly ApprovalForecastProjection $approvalForecast,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(EstimateDocument $document, ?User $user = null): array
    {
        $document->loadMissing('repairOrder');

        if ($this->usesStoredSnapshot($document)) {
            return $this->resolveStoredSnapshot($document, $user);
        }

        return $this->invoiceFinancialSnapshot->append(
            $document,
            $this->snapshotBuilder->build($document->repairOrder, $user),
        );
    }

    private function usesStoredSnapshot(EstimateDocument $document): bool
    {
        if ($document->isInvoice()) {
            return true;
        }

        if (data_get($document->snapshot_json, 'schema_version') === 'legacy_import') {
            return true;
        }

        return $document->repairOrder?->estimateDocumentIsFrozen() === true;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveStoredSnapshot(EstimateDocument $document, ?User $user = null): array
    {
        $stored = $document->snapshot_json;

        if (is_array($stored) && data_get($stored, 'repair_order') !== null) {
            return $this->invoiceFinancialSnapshot->append(
                $document,
                RepairOrderFreeText::normalizeSnapshot(
                    $this->withLiveShopPresentation(
                        $this->withLiveConcernIntent(
                            $document,
                            $this->withLiveDocumentLineLabels(
                                $this->withLiveCustomerIdentity(
                                    $document,
                                    $this->withLiveVisitIdentity(
                                        $document,
                                        $this->withLiveStaffIdentity(
                                            $document,
                                            $this->withLiveRepairOrderIdentity(
                                                $document,
                                                $this->withLiveApprovalForecast(
                                                    $document,
                                                    $this->withLiveVehicleIdentity($document, $stored),
                                                ),
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            );
        }

        $built = $this->snapshotBuilder->build($document->repairOrder, $user);

        if (is_array($stored) && data_get($stored, 'schema_version') === 'legacy_import') {
            $legacyTotals = data_get($stored, 'totals');

            if (is_array($legacyTotals)) {
                $built['totals'] = array_replace($built['totals'] ?? [], $legacyTotals);
            }
        }

        return $built;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withLiveShopPresentation(array $snapshot): array
    {
        $snapshot['shop'] = [
            ...((array) ($snapshot['shop'] ?? [])),
            ...$this->snapshotBuilder->presentationLayers()['shop'],
        ];

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withLiveApprovalForecast(EstimateDocument $document, array $snapshot): array
    {
        $document->loadMissing(['repairOrder.concerns.lines', 'repairOrder.lines.concern']);

        $repairOrder = $document->repairOrder;

        if (! $repairOrder instanceof RepairOrder) {
            return $snapshot;
        }

        $snapshot['approval_forecast'] = $this->approvalForecast->for($repairOrder);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withLiveVehicleIdentity(EstimateDocument $document, array $snapshot): array
    {
        $document->loadMissing('repairOrder.vehicle');

        $repairOrder = $document->repairOrder;
        $vehicle = $repairOrder?->vehicle;

        if (! $repairOrder instanceof RepairOrder || $vehicle === null) {
            return $snapshot;
        }

        $snapshot['vehicle'] = [
            ...($snapshot['vehicle'] ?? []),
            ...$this->snapshotBuilder->vehicleIdentitySnapshot($repairOrder),
        ];

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withLiveStaffIdentity(EstimateDocument $document, array $snapshot): array
    {
        $document->loadMissing('repairOrder.assignedTechnician');

        $repairOrder = $document->repairOrder;

        if (! $repairOrder instanceof RepairOrder) {
            return $snapshot;
        }

        $technicianName = $repairOrder->technicianOwnershipLabel();

        $snapshot['staff'] = [
            ...((array) ($snapshot['staff'] ?? [])),
            'execution' => [
                ...((array) data_get($snapshot, 'staff.execution', [])),
                'technician_name' => $technicianName,
            ],
        ];

        $snapshot['repair_order'] = [
            ...((array) ($snapshot['repair_order'] ?? [])),
            'assigned_technician_name' => $technicianName,
        ];

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withLiveCustomerIdentity(EstimateDocument $document, array $snapshot): array
    {
        $document->loadMissing('repairOrder.customer');

        $customer = $document->repairOrder?->customer;

        if ($customer === null) {
            return $snapshot;
        }

        $snapshot['customer'] = [
            ...($snapshot['customer'] ?? []),
            'name' => $customer->name,
            'phone' => $customer->display_phone ?? $customer->phone,
            'email' => $customer->email,
            'display_address' => $customer->display_address,
            'address_line_1' => $customer->address_line_1,
            'address_line_2' => $customer->address_line_2,
            'city' => $customer->city,
            'state' => $customer->state,
            'postal_code' => $customer->postal_code,
            'customer_type' => $customer->customer_type,
        ];

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withLiveRepairOrderIdentity(EstimateDocument $document, array $snapshot): array
    {
        $document->loadMissing('repairOrder');

        $repairOrder = $document->repairOrder;

        if (! $repairOrder instanceof RepairOrder) {
            return $snapshot;
        }

        $snapshot['repair_order'] = [
            ...((array) ($snapshot['repair_order'] ?? [])),
            'id' => $repairOrder->id,
            'repair_order_id' => $repairOrder->repair_order_id,
        ];

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withLiveVisitIdentity(EstimateDocument $document, array $snapshot): array
    {
        if (filled(data_get($snapshot, 'generated_by.name'))) {
            return $snapshot;
        }

        $document->loadMissing([
            'repairOrder.encounter.creator',
            'repairOrder.estimateDocuments.creator',
        ]);

        $repairOrder = $document->repairOrder;

        if (! $repairOrder instanceof RepairOrder) {
            return $snapshot;
        }

        $advisorName = $repairOrder->serviceAdvisorName();

        if (! filled($advisorName)) {
            return $snapshot;
        }

        $snapshot['generated_by'] = [
            ...((array) ($snapshot['generated_by'] ?? [])),
            'name' => $advisorName,
        ];

        $snapshot['repair_order'] = [
            ...((array) ($snapshot['repair_order'] ?? [])),
            'advisor_name' => $advisorName,
        ];

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withLiveConcernIntent(EstimateDocument $document, array $snapshot): array
    {
        $concerns = data_get($snapshot, 'concerns');

        if (! is_array($concerns)) {
            return $snapshot;
        }

        $concernIds = collect($concerns)
            ->map(fn (mixed $concern): ?int => is_array($concern) && is_numeric($concern['id'] ?? null) ? (int) $concern['id'] : null)
            ->filter()
            ->values()
            ->all();

        if ($concernIds === []) {
            return $snapshot;
        }

        $liveById = RepairOrderConcern::query()
            ->whereIn('id', $concernIds)
            ->get()
            ->keyBy('id');

        foreach ($concerns as $index => $concern) {
            if (! is_array($concern)) {
                continue;
            }

            $live = $liveById->get((int) ($concern['id'] ?? 0));

            if (! $live instanceof RepairOrderConcern) {
                continue;
            }

            $intent = $live->recommendationIntent();
            $concerns[$index]['recommendation_intent'] = $intent->value;
            $concerns[$index]['recommendation_intent_label'] = $intent->staffLabel();
            $concerns[$index]['disposition'] = $live->disposition->value;
            $concerns[$index]['disposition_label'] = $live->disposition->label();
        }

        $snapshot['concerns'] = $concerns;

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withLiveDocumentLineLabels(array $snapshot): array
    {
        $concerns = data_get($snapshot, 'concerns');

        if (! is_array($concerns)) {
            return $snapshot;
        }

        $lineIds = [];

        foreach ($concerns as $concern) {
            foreach ($this->collectSnapshotLineIds($concern) as $lineId) {
                $lineIds[] = $lineId;
            }
        }

        if ($lineIds === []) {
            return $snapshot;
        }

        $liveById = RepairOrderLine::query()
            ->whereIn('id', array_values(array_unique($lineIds)))
            ->get()
            ->keyBy('id');

        foreach ($concerns as $concernIndex => $concern) {
            if (! is_array($concern)) {
                continue;
            }

            $concerns[$concernIndex] = $this->mergeLiveLinePresentation($concern, $liveById);
        }

        $snapshot['concerns'] = $concerns;

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $concern
     * @return list<int>
     */
    private function collectSnapshotLineIds(array $concern): array
    {
        $lineIds = [];

        foreach ((array) data_get($concern, 'lines', []) as $line) {
            $id = data_get($line, 'id');

            if (is_numeric($id)) {
                $lineIds[] = (int) $id;
            }
        }

        foreach ((array) data_get($concern, 'work_groups', []) as $workGroup) {
            foreach ((array) data_get($workGroup, 'lines', []) as $line) {
                $id = data_get($line, 'id');

                if (is_numeric($id)) {
                    $lineIds[] = (int) $id;
                }
            }
        }

        return $lineIds;
    }

    /**
     * @param  array<string, mixed>  $concern
     * @param  Collection<int, RepairOrderLine>  $liveById
     * @return array<string, mixed>
     */
    private function mergeLiveLinePresentation(array $concern, $liveById): array
    {
        $lines = data_get($concern, 'lines');

        if (is_array($lines)) {
            foreach ($lines as $lineIndex => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $lines[$lineIndex] = $this->mergeLiveLineFields($line, $liveById);
            }

            $concern['lines'] = $lines;
        }

        $workGroups = data_get($concern, 'work_groups');

        if (is_array($workGroups)) {
            foreach ($workGroups as $groupIndex => $workGroup) {
                if (! is_array($workGroup)) {
                    continue;
                }

                $groupLines = data_get($workGroup, 'lines');

                if (! is_array($groupLines)) {
                    continue;
                }

                foreach ($groupLines as $lineIndex => $line) {
                    if (! is_array($line)) {
                        continue;
                    }

                    $groupLines[$lineIndex] = $this->mergeLiveLineFields($line, $liveById);
                }

                $workGroup['lines'] = $groupLines;
                $workGroups[$groupIndex] = $workGroup;
            }

            $concern['work_groups'] = $workGroups;
        }

        return $concern;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  Collection<int, RepairOrderLine>  $liveById
     * @return array<string, mixed>
     */
    private function mergeLiveLineFields(array $line, $liveById): array
    {
        $live = $liveById->get((int) data_get($line, 'id'));

        if (! $live instanceof RepairOrderLine) {
            return $line;
        }

        $line['type'] = $live->type->value;
        $line['type_label'] = $live->type->documentLabel();
        $line['description'] = $live->description;
        $line['customer_description'] = $live->customer_description;

        return $line;
    }
}
