<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\PartLineClassification;
use App\Ark\Operations\RepairOrders\PartLineSource;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderLinePricing;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class CaptureDealerQuoteAction
{
    public function __construct(
        private readonly DealerQuoteTextExtractor $extractor,
        private readonly DealerQuoteParser $parser,
        private readonly RepairOrderLinePricing $pricing,
        private readonly EstimateTotalsCalculator $calculator,
        private readonly EstimateDocumentService $documents,
        private readonly OperationalEventRecorder $events,
        private readonly RepairOrderLifecycleTransition $lifecycle,
    ) {}

    /**
     * @return array{
     *     supplier_name: ?string,
     *     quote_number: ?string,
     *     vehicle_description: ?string,
     *     vin: ?string,
     *     dealer_total_cents: ?int,
     *     dealer_total: ?string,
     *     lines: list<array<string, mixed>>,
     *     raw_text: string,
     *     original_filename: ?string,
     *     temp_storage_path: ?string
     * }
     */
    public function analyze(RepairOrder $repairOrder, ?UploadedFile $file, ?string $pastedText): array
    {
        $repairOrder->ensureOpenForEditing();

        $rawText = $this->extractor->fromUpload($file, $pastedText);
        $parsed = $this->parser->parse($rawText);

        $tempPath = null;
        $originalFilename = null;

        if ($file !== null) {
            $originalFilename = $file->getClientOriginalName();
            $extension = strtolower((string) $file->getClientOriginalExtension()) ?: 'bin';
            $tempPath = 'dealer-quotes/tmp/'.$repairOrder->id.'/'.Str::uuid()->toString().'.'.$extension;
            Storage::disk('local')->put($tempPath, file_get_contents($file->getRealPath() ?: $file->getPathname()) ?: '');
        }

        return [
            'supplier_name' => $parsed['supplier_name'],
            'quote_number' => $parsed['quote_number'],
            'vehicle_description' => $parsed['vehicle_description'],
            'vin' => $parsed['vin'],
            'dealer_total_cents' => $parsed['dealer_total_cents'],
            'dealer_total' => $parsed['dealer_total_cents'] !== null
                ? number_format($parsed['dealer_total_cents'] / 100, 2, '.', '')
                : null,
            'lines' => $parsed['lines'],
            'raw_text' => $rawText,
            'original_filename' => $originalFilename,
            'temp_storage_path' => $tempPath,
        ];
    }

    /**
     * @param  list<array{
     *     source_key: string,
     *     repair_order_concern_id: int,
     *     repair_order_work_group_id?: int|null,
     *     part_cost?: string|null
     * }>  $assignments
     * @param  array{
     *     supplier_name?: ?string,
     *     quote_number?: ?string,
     *     vehicle_description?: ?string,
     *     vin?: ?string,
     *     dealer_total_cents?: ?int,
     *     raw_text: string,
     *     original_filename?: ?string,
     *     temp_storage_path?: ?string,
     *     lines: list<array{
     *         source_key: string,
     *         quantity: string,
     *         part_number?: ?string,
     *         description: string,
     *         part_cost: string,
     *         unit_cost_cents?: int,
     *         extended_cost_cents?: ?int
     *     }>
     * }  $capture
     * @return array{imported: int, concerns: list<string>, work_group_ids: list<int>, dealer_quote_id: int}
     */
    public function import(RepairOrder $repairOrder, array $assignments, array $capture, User $actor): array
    {
        $repairOrder->ensureOpenForEditing();

        if ($assignments === []) {
            throw new RuntimeException('Select at least one part from the dealer quote.');
        }

        $previewLines = collect($capture['lines'] ?? [])->keyBy(fn (array $line): string => (string) $line['source_key']);

        if ($previewLines->isEmpty()) {
            throw new RuntimeException('Dealer quote changed. Analyze again and try once more.');
        }

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
        $dealerQuoteId = 0;

        DB::transaction(function () use (
            $repairOrder,
            $assignments,
            $capture,
            $previewLines,
            $concernIds,
            $actor,
            &$imported,
            &$concernSummaries,
            &$workGroupIds,
            &$dealerQuoteId,
        ): void {
            $storagePath = $this->persistOriginalDocument(
                $repairOrder,
                $capture['temp_storage_path'] ?? null,
                $capture['original_filename'] ?? null,
            );

            $quote = DealerQuote::query()->create([
                'repair_order_id' => $repairOrder->id,
                'supplier_name' => $this->nullableString($capture['supplier_name'] ?? null),
                'quote_number' => $this->nullableString($capture['quote_number'] ?? null),
                'vehicle_description' => $this->nullableString($capture['vehicle_description'] ?? null),
                'vin' => $this->nullableString($capture['vin'] ?? null),
                'dealer_total_cents' => isset($capture['dealer_total_cents']) ? (int) $capture['dealer_total_cents'] : null,
                'original_filename' => $this->nullableString($capture['original_filename'] ?? null),
                'storage_path' => $storagePath,
                'raw_text' => (string) ($capture['raw_text'] ?? ''),
                'captured_by_user_id' => $actor->id,
                'captured_at' => now(),
            ]);

            $dealerQuoteId = (int) $quote->id;
            $quoteLineBySourceKey = [];
            $position = 0;

            foreach ($previewLines as $sourceKey => $previewLine) {
                $position++;
                $unitCostCents = isset($previewLine['unit_cost_cents'])
                    ? (int) $previewLine['unit_cost_cents']
                    : (int) round(((float) $previewLine['part_cost']) * 100);

                $quoteLine = $quote->lines()->create([
                    'position' => $position,
                    'quantity' => $previewLine['quantity'],
                    'part_number' => $this->nullableString($previewLine['part_number'] ?? null),
                    'description' => (string) $previewLine['description'],
                    'unit_cost_cents' => $unitCostCents,
                    'extended_cost_cents' => isset($previewLine['extended_cost_cents'])
                        ? (int) $previewLine['extended_cost_cents']
                        : null,
                ]);

                $quoteLineBySourceKey[$sourceKey] = $quoteLine;
            }

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

                $quoteLine = $quoteLineBySourceKey[$sourceKey] ?? null;

                if ($quoteLine === null) {
                    throw new RuntimeException('Dealer quote changed. Analyze again and try once more.');
                }

                $this->createPartLine($repairOrder, $concernId, $workGroupId, $quote, $quoteLine, $assignment, $actor);
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
                throw new RuntimeException('Select at least one part from the dealer quote.');
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
            'dealer_quote_id' => $dealerQuoteId,
        ];
    }

    private function persistOriginalDocument(RepairOrder $repairOrder, ?string $tempPath, ?string $originalFilename): ?string
    {
        if ($tempPath === null || $tempPath === '') {
            return null;
        }

        if (! Storage::disk('local')->exists($tempPath)) {
            return null;
        }

        $extension = strtolower(pathinfo((string) $originalFilename, PATHINFO_EXTENSION) ?: 'pdf');
        $permanent = 'dealer-quotes/'.$repairOrder->id.'/'.Str::uuid()->toString().'.'.$extension;
        Storage::disk('local')->move($tempPath, $permanent);

        return $permanent;
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
            throw new RuntimeException('Repair action must belong to the same scope as the captured part.');
        }

        if (! $workGroup->hasPartsAttachAnchor()) {
            throw new RuntimeException('Parts can only add into repair actions that already have labor or a package.');
        }
    }

    /**
     * @param  array<string, mixed>  $assignment
     */
    private function createPartLine(
        RepairOrder $repairOrder,
        int $concernId,
        ?int $workGroupId,
        DealerQuote $quote,
        DealerQuoteLine $quoteLine,
        array $assignment,
        User $actor,
    ): void {
        $supplier = trim((string) $quote->supplier_name);
        $quoteNumber = trim((string) $quote->quote_number);
        $sourcingNotes = 'Dealer Quote'
            .($supplier !== '' ? ' · '.$supplier : '')
            .($quoteNumber !== '' ? ' · '.$quoteNumber : '');

        $description = filled($assignment['description'] ?? null)
            ? trim((string) $assignment['description'])
            : (string) $quoteLine->description;

        $data = [
            'type' => RepairOrderLineType::Part->value,
            'description' => $description !== '' ? $description : (string) $quoteLine->description,
            'quantity' => (string) $quoteLine->quantity,
            'part_cost' => filled($assignment['part_cost'] ?? null)
                ? (string) $assignment['part_cost']
                : $quoteLine->unitCostDecimal(),
            'vendor_name' => $supplier !== '' ? $supplier : null,
            'part_number' => $quoteLine->part_number,
            'sourcing_notes' => $sourcingNotes,
            'repair_order_concern_id' => $concernId,
            'pricing_mode' => 'matrix',
            'part_source' => PartLineSource::ShopSupplied->value,
            'part_classification' => PartLineClassification::Oem->value,
        ];

        if (filled($assignment['pricing_matrix_key'] ?? null)) {
            $data['pricing_matrix_key'] = (string) $assignment['pricing_matrix_key'];
            $data['pricing_matrix_explicit'] = true;
        }

        $data = RepairOrderLineType::Part->applyInputDefaults($data);
        $pricingAttributes = $this->pricing->attributesFor($data, $repairOrder);

        $line = $repairOrder->lines()->create([
            'repair_order_concern_id' => $concernId,
            'repair_order_work_group_id' => $workGroupId,
            'type' => RepairOrderLineType::Part,
            'description' => $data['description'],
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
            'dealer_quote_line_id' => $quoteLine->id,
            'sourcing_notes' => $pricingAttributes['sourcing_notes'],
            'part_source' => PartLineSource::ShopSupplied,
            'part_classification' => PartLineClassification::Oem,
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
                'source' => 'dealer_quote',
                'dealer_quote_id' => $quote->id,
                'dealer_quote_line_id' => $quoteLine->id,
                'subtotal_cents' => $line->subtotal_cents,
                'total_cents' => $line->total_cents,
            ],
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
