<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderLinePricing;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PartsTechQuoteImporter
{
    public function __construct(
        private readonly PartsTechActiveCartQuoteReader $reader,
        private readonly RepairOrderLinePricing $pricing,
        private readonly EstimateTotalsCalculator $calculator,
        private readonly EstimateDocumentService $documents,
        private readonly OperationalEventRecorder $events,
        private readonly RepairOrderLifecycleTransition $lifecycle,
        private readonly CustomerPartDescriptionAttributes $customerDescriptions,
    ) {}

    /**
     * @param  list<array{source_key: string, repair_order_concern_id: int, repair_order_work_group_id?: int|null, part_cost?: string|null, pricing_matrix_key?: string|null}>  $assignments
     * @return array{imported: int, concerns: list<string>, work_group_ids: list<int>}
     */
    public function importAssignments(RepairOrder $repairOrder, array $assignments, User $actor): array
    {
        $repairOrder->ensureOpenForEditing();

        if ($assignments === []) {
            throw new RuntimeException('Select at least one PartsTech part to import.');
        }

        /** @var Collection<string, PartsTechQuoteLine> $quoteByKey */
        $quoteByKey = collect($this->reader->linesForRepairOrder($repairOrder))
            ->keyBy(fn (PartsTechQuoteLine $line): string => $line->sourceKey);

        $concernIds = RepairOrderConcern::query()
            ->where('repair_order_id', $repairOrder->id)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $imported = 0;
        /** @var array<int, string> $concernSummaries */
        $concernSummaries = [];
        /** @var list<int> $workGroupIds */
        $workGroupIds = [];

        DB::transaction(function () use ($repairOrder, $assignments, $quoteByKey, $concernIds, $actor, &$imported, &$concernSummaries, &$workGroupIds): void {
            foreach ($assignments as $assignment) {
                $sourceKey = (string) ($assignment['source_key'] ?? '');
                $concernId = (int) ($assignment['repair_order_concern_id'] ?? 0);
                $workGroupId = filled($assignment['repair_order_work_group_id'] ?? null)
                    ? (int) $assignment['repair_order_work_group_id']
                    : null;

                if ($sourceKey === '' || $concernId <= 0) {
                    continue;
                }

                if (! in_array($concernId, $concernIds, true)) {
                    throw new RuntimeException('One or more selected concerns are not on this repair order.');
                }

                $this->assertWorkGroupAssignment($concernId, $workGroupId);

                $quoteLine = $quoteByKey->get($sourceKey);

                if ($quoteLine === null) {
                    throw new RuntimeException('PartsTech quote changed. Reload the quote and try again.');
                }

                $this->createPartLine($repairOrder, $concernId, $workGroupId, $quoteLine, $assignment, $actor);
                $imported++;

                if ($workGroupId !== null) {
                    $workGroupIds[] = $workGroupId;
                }

                if (! isset($concernSummaries[$concernId])) {
                    $concernSummaries[$concernId] = (string) RepairOrderConcern::query()
                        ->whereKey($concernId)
                        ->value('summary');
                }
            }

            if ($imported === 0) {
                throw new RuntimeException('Select at least one PartsTech part to import.');
            }

            $this->calculator->recalculateRepairOrder($repairOrder);

            if ($repairOrder->status->is(RepairOrderStatus::Draft)) {
                $this->lifecycle->move($repairOrder, RepairOrderStatus::Estimate, $actor);
            }

            $this->documents->markDirtyForRepairOrder($repairOrder);
        });

        return [
            'imported' => $imported,
            'concerns' => array_values($concernSummaries),
            'work_group_ids' => array_values(array_unique($workGroupIds)),
        ];
    }

    private function assertWorkGroupAssignment(int $concernId, ?int $workGroupId): void
    {
        if ($workGroupId === null) {
            return;
        }

        $workGroup = RepairOrderWorkGroup::query()
            ->with('lines')
            ->find($workGroupId);

        if ($workGroup === null || (int) $workGroup->repair_order_concern_id !== $concernId) {
            throw new RuntimeException('Repair action must belong to the same scope as the imported part.');
        }

        if (! $workGroup->hasPartsAttachAnchor()) {
            throw new RuntimeException('Parts can only import into repair actions that already have labor or a package.');
        }
    }

    /**
     * @param  array<string, mixed>  $assignment
     */
    private function createPartLine(
        RepairOrder $repairOrder,
        int $concernId,
        ?int $workGroupId,
        PartsTechQuoteLine $quoteLine,
        array $assignment,
        User $actor,
    ): void {
        $data = [
            'type' => RepairOrderLineType::Part->value,
            'description' => $quoteLine->description,
            'quantity' => $quoteLine->quantity,
            'part_cost' => filled($assignment['part_cost'] ?? null)
                ? (string) $assignment['part_cost']
                : $quoteLine->partCost,
            'vendor_name' => $quoteLine->vendorName,
            'part_number' => $quoteLine->partNumber,
            'sourcing_notes' => $quoteLine->sourcingNotes,
            'repair_order_concern_id' => $concernId,
            'pricing_mode' => 'matrix',
        ];

        if (filled($assignment['pricing_matrix_key'] ?? null)) {
            $data['pricing_matrix_key'] = (string) $assignment['pricing_matrix_key'];
            $data['pricing_matrix_explicit'] = true;
        }

        $data = RepairOrderLineType::Part->applyInputDefaults($data);
        $pricingAttributes = $this->pricing->attributesFor($data, $repairOrder);
        $customerDescription = $this->customerDescriptions->forCreate(
            inventoryDescription: (string) $data['description'],
            brand: $quoteLine->brandName,
        );

        $line = $repairOrder->lines()->create([
            'repair_order_concern_id' => $concernId,
            'repair_order_work_group_id' => $workGroupId,
            'type' => RepairOrderLineType::Part,
            'description' => $data['description'],
            'customer_description' => $customerDescription['customer_description'],
            'customer_description_source' => $customerDescription['customer_description_source'],
            'quantity' => $data['quantity'],
            'unit_price_cents' => $pricingAttributes['unit_price_cents'],
            'part_cost_cents' => $pricingAttributes['part_cost_cents'],
            'matrix_suggested_price_cents' => $pricingAttributes['matrix_suggested_price_cents'],
            'pricing_mode' => $pricingAttributes['pricing_mode'],
            'pricing_matrix_key' => $pricingAttributes['pricing_matrix_key'],
            'pricing_matrix_name' => $pricingAttributes['pricing_matrix_name'],
            'matrix_applied' => $pricingAttributes['matrix_applied'],
            'vendor_name' => $pricingAttributes['vendor_name'],
            'part_number' => $pricingAttributes['part_number'],
            'sourcing_notes' => $pricingAttributes['sourcing_notes'],
            'is_overridden' => $pricingAttributes['is_overridden'],
            'subtotal_cents' => $this->calculator->lineTotalCents($data['quantity'], $pricingAttributes['unit_price_cents']),
        ]);

        $this->events->record(
            OperationalEventName::EstimateLineAdded,
            $repairOrder,
            actor: $actor,
            payload: [
                'line_id' => $line->id,
                'concern_id' => $concernId,
                'type' => $line->type->value,
                'source' => 'partstech',
                'subtotal_cents' => $line->subtotal_cents,
                'total_cents' => $line->total_cents,
            ],
        );
    }

}
