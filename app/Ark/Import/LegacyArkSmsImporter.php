<?php

namespace App\Ark\Import;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class LegacyArkSmsImporter
{
    public function __construct(
        private readonly LegacyArkSmsReader $reader,
        private readonly LegacyArkSmsValueMapper $mapper,
    ) {}

    public function auditSchema(): array
    {
        $audit = [];

        foreach (['customers', 'vehicles', 'repair_orders', 'concerns', 'lines', 'invoices'] as $key) {
            $table = LegacyArkSmsImportConfig::table($key);
            $audit[$key] = [
                'configured_table' => $table,
                'exists' => in_array($table, $this->reader->tableNames(), true),
                'columns' => $this->safeColumnNames($table),
                'expected_columns' => array_values(LegacyArkSmsImportConfig::columns($key)),
            ];
        }

        return $audit;
    }

    public function wipeImported(LegacyImportReport $report, bool $dryRun): void
    {
        $entities = [
            ['model' => EstimateDocument::class, 'column' => 'legacy_arksms_invoice_id', 'label' => 'invoices'],
            ['model' => RepairOrderLine::class, 'column' => 'legacy_arksms_line_id', 'label' => 'lines'],
            ['model' => RepairOrderConcern::class, 'column' => 'legacy_arksms_concern_id', 'label' => 'concerns'],
            ['model' => RepairOrder::class, 'column' => 'repair_order_id', 'label' => 'repair_orders'],
            ['model' => Vehicle::class, 'column' => 'legacy_arksms_vehicle_id', 'label' => 'vehicles'],
            ['model' => Customer::class, 'column' => 'legacy_arksms_customer_id', 'label' => 'customers'],
        ];

        if ($dryRun) {
            foreach ($entities as $entity) {
                $count = $entity['model']::query()->whereNotNull($entity['column'])->count();
                $report->addWarning("Dry run: would delete {$count} imported {$entity['label']}.");
            }

            return;
        }

        DB::transaction(function () use ($entities): void {
            foreach ($entities as $entity) {
                $entity['model']::query()->whereNotNull($entity['column'])->delete();
            }
        });
    }

    public function run(LegacyImportOptions $options, LegacyImportReport $report): void
    {
        $report->dryRun = $options->dryRun;
        $checkpoint = $options->resume ? LegacyImportCheckpoint::load() : new LegacyImportCheckpoint;

        $remaining = $options->limit;

        foreach ($this->reader->customers(
            $options->resume ? $checkpoint->offsets['customers'] : null,
            $options->legacyCustomerId,
            $remaining,
        ) as $legacyCustomer) {
            $this->importCustomer($legacyCustomer, $options, $report);

            if (! $options->dryRun) {
                $checkpoint->bump('customers', (int) $legacyCustomer['id']);
            }

            if ($remaining !== null) {
                $remaining--;

                if ($remaining <= 0) {
                    break;
                }
            }
        }

        $vehicleLimit = $options->limit;
        foreach ($this->reader->vehicles(
            $options->resume ? $checkpoint->offsets['vehicles'] : null,
            $options->legacyCustomerId,
            $vehicleLimit,
        ) as $legacyVehicle) {
            $this->importVehicle($legacyVehicle, $options, $report);

            if (! $options->dryRun) {
                $checkpoint->bump('vehicles', (int) $legacyVehicle['id']);
            }
        }

        $roLimit = $options->limit;
        foreach ($this->reader->repairOrders(
            $options->resume ? $checkpoint->offsets['repair_orders'] : null,
            $options->legacyCustomerId,
            $roLimit,
        ) as $legacyRepairOrder) {
            $this->importRepairOrder($legacyRepairOrder, $options, $report);

            if (! $options->dryRun) {
                $checkpoint->bump('repair_orders', (int) $legacyRepairOrder['id']);
            }
        }

        if (! $options->dryRun) {
            $checkpoint->save();
        }

        $this->persistReport($report);
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function importCustomer(array $legacy, LegacyImportOptions $options, LegacyImportReport $report): void
    {
        $legacyId = (int) ($legacy['id'] ?? 0);

        if ($legacyId <= 0) {
            $report->skip('Customer row missing id.');

            return;
        }

        if (trim((string) ($legacy['first_name'] ?? '')) === '' && trim((string) ($legacy['last_name'] ?? '')) === '') {
            $report->skip("Customer {$legacyId}: missing name.");

            return;
        }

        $existing = Customer::query()->where('legacy_arksms_customer_id', $legacyId)->first();

        $attributes = [
            'legacy_arksms_customer_id' => $legacyId,
            'first_name' => trim((string) ($legacy['first_name'] ?? 'Unknown')),
            'last_name' => trim((string) ($legacy['last_name'] ?? 'Customer')),
            'phone' => $this->mapper->normalizePhone($legacy['phone'] ?? null),
            'email' => $this->mapper->normalizeEmail($legacy['email'] ?? null),
            'customer_type' => $this->mapper->mapCustomerType($legacy['customer_type'] ?? null),
            'notes' => $legacy['notes'] ?? null,
        ];

        if ($attributes['email'] === null && filled($legacy['email'] ?? null)) {
            $report->addWarning("Customer {$legacyId}: invalid email dropped.");
        }

        if ($options->dryRun) {
            $report->customersImported++;

            return;
        }

        $this->persistModel($existing, Customer::class, $attributes, $legacy, $report, 'customers');
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function importVehicle(array $legacy, LegacyImportOptions $options, LegacyImportReport $report): void
    {
        $legacyId = (int) ($legacy['id'] ?? 0);
        $legacyCustomerId = (int) ($legacy['customer_id'] ?? 0);

        if ($legacyId <= 0 || $legacyCustomerId <= 0) {
            $report->skip("Vehicle {$legacyId}: missing id or customer_id.");

            return;
        }

        if ($options->dryRun) {
            $report->vehiclesImported++;

            return;
        }

        $customer = Customer::query()->where('legacy_arksms_customer_id', $legacyCustomerId)->first();

        if (! $customer) {
            $report->skip("Vehicle {$legacyId}: customer {$legacyCustomerId} not imported yet.");

            return;
        }

        $existing = Vehicle::query()->where('legacy_arksms_vehicle_id', $legacyId)->first();
        $plate = $legacy['plate'] ?? $legacy['license_plate'] ?? null;
        $privateNotes = $this->mapper->appendMileageNote($legacy['private_notes'] ?? null, $legacy['mileage'] ?? null);

        $attributes = [
            'legacy_arksms_vehicle_id' => $legacyId,
            'customer_id' => $customer->id,
            'vin' => $this->mapper->normalizeVin($legacy['vin'] ?? null, $report, "Vehicle {$legacyId}"),
            'plate' => $plate,
            'plate_state' => $legacy['plate_state'] ?? $legacy['license_plate_state'] ?? null,
            'year' => filled($legacy['year'] ?? null) ? (int) $legacy['year'] : null,
            'make' => $legacy['make'] ?? null,
            'model' => $legacy['model'] ?? null,
            'trim' => $legacy['trim'] ?? null,
            'engine' => $legacy['engine'] ?? null,
            'transmission' => $this->mapper->normalizeTransmission($legacy['transmission'] ?? null),
            'drive' => $this->mapper->normalizeDrive($legacy['drive'] ?? $legacy['drivetrain'] ?? null),
            'color' => $legacy['color'] ?? null,
            'nickname' => $legacy['nickname'] ?? null,
            'public_notes' => $legacy['public_notes'] ?? $legacy['notes'] ?? null,
            'private_notes' => $privateNotes,
        ];

        $this->persistModel($existing, Vehicle::class, $attributes, $legacy, $report, 'vehicles');
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function importRepairOrder(array $legacy, LegacyImportOptions $options, LegacyImportReport $report): void
    {
        $legacyInternalId = LegacyRepairOrderRecord::internalId($legacy);
        $legacyShopNumber = LegacyRepairOrderRecord::shopNumber($legacy);
        $legacyCustomerId = (int) ($legacy['customer_id'] ?? 0);
        $legacyVehicleId = (int) ($legacy['vehicle_id'] ?? 0);

        if ($legacyInternalId <= 0 || $legacyShopNumber <= 0) {
            $report->skip('Repair order missing id.');

            return;
        }

        if ($options->dryRun) {
            $report->repairOrdersImported++;
            $this->countRepairOrderChildren($legacyInternalId, $report);

            return;
        }

        $customer = Customer::query()->where('legacy_arksms_customer_id', $legacyCustomerId)->first();
        $vehicle = Vehicle::query()->where('legacy_arksms_vehicle_id', $legacyVehicleId)->first();

        if (! $customer || ! $vehicle) {
            $report->skip("RO {$legacyShopNumber}: missing customer or vehicle mapping.");

            return;
        }

        $concernSummary = trim((string) ($legacy['concern_summary'] ?? $legacy['concern'] ?? $legacy['customer_concern'] ?? ''));

        if ($concernSummary === '') {
            $concernSummary = 'Imported legacy repair order.';
        }

        $status = $this->mapper->mapRepairOrderStatus($legacy['status'] ?? null, $report);
        $legacyStatusSlug = strtolower(trim((string) ($legacy['status'] ?? '')));
        $paymentStatus = $this->mapper->mapPaymentStatus(
            $legacy['payment_status'] ?? null,
            $legacy['paid'] ?? $legacy['paid_at'] ?? $legacy['invoice_paid_at'] ?? null,
        );

        $existing = RepairOrder::query()->where('repair_order_id', $legacyShopNumber)->first();

        $attributes = [
            'repair_order_id' => $legacyShopNumber,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => $status->value,
            'payment_status' => $paymentStatus->value,
            'paid_at' => $paymentStatus->value === 'paid'
                ? $this->parseTimestamp($legacy['paid_at'] ?? $legacy['invoice_paid_at'] ?? $legacy['updated_at'] ?? null)
                : null,
            'concern_summary' => $concernSummary,
            ...$this->mapper->repairOrderMileage($legacy),
            'opened_at' => LegacyRepairOrderTimeline::openedAt($legacy),
            'closed_at' => LegacyRepairOrderTimeline::closedAt($legacy, $status, $legacyStatusSlug),
        ];

        $repairOrder = $this->persistModel($existing, RepairOrder::class, $attributes, $legacy, $report, 'repair_orders');

        if (! $repairOrder instanceof RepairOrder) {
            return;
        }

        $this->importConcernsAndLines($repairOrder, $legacyInternalId, $legacy, $options, $report);
        $this->importInvoices($repairOrder, $legacyInternalId, $options, $report);
    }

    private function importConcernsAndLines(
        RepairOrder $repairOrder,
        int $legacyRepairOrderId,
        array $legacyRo,
        LegacyImportOptions $options,
        LegacyImportReport $report,
    ): void {
        $concerns = iterator_to_array($this->reader->concernsForRepairOrder($legacyRepairOrderId));
        $defaultConcern = null;

        if ($concerns === []) {
            $defaultConcern = $this->upsertConcern($repairOrder, [
                'id' => null,
                'summary' => $repairOrder->concern_summary,
                'notes' => $legacyRo['notes'] ?? null,
                'disposition' => 'recommended',
                'position' => 0,
            ], $options, $report);
        } else {
            foreach ($concerns as $legacyConcern) {
                $concern = $this->upsertConcern($repairOrder, $legacyConcern, $options, $report);

                if ($defaultConcern === null) {
                    $defaultConcern = $concern;
                }
            }
        }

        foreach ($this->reader->linesForRepairOrder($legacyRepairOrderId) as $legacyLine) {
            $this->importLine($repairOrder, $defaultConcern, $legacyLine, $options, $report);
        }
    }

    /**
     * @param  array<string, mixed>  $legacyConcern
     */
    private function upsertConcern(
        RepairOrder $repairOrder,
        array $legacyConcern,
        LegacyImportOptions $options,
        LegacyImportReport $report,
    ): ?RepairOrderConcern {
        $legacyConcernId = isset($legacyConcern['id']) ? (int) $legacyConcern['id'] : null;

        $existing = $legacyConcernId
            ? RepairOrderConcern::query()->where('legacy_arksms_concern_id', $legacyConcernId)->first()
            : null;

        $attributes = [
            'legacy_arksms_concern_id' => $legacyConcernId,
            'repair_order_id' => $repairOrder->id,
            'summary' => trim((string) ($legacyConcern['summary'] ?? $repairOrder->concern_summary)),
            'notes' => $legacyConcern['notes'] ?? null,
            'disposition' => $this->mapper->mapDisposition($legacyConcern['disposition'] ?? null)->value,
            'recommendation_intent' => 'maintenance',
            'position' => (int) ($legacyConcern['position'] ?? 0),
        ];

        if ($options->dryRun) {
            $report->concernsImported++;

            return null;
        }

        if ($existing) {
            $existing->fill($attributes);
            $existing->save();
            $report->concernsImported++;

            return $existing;
        }

        $concern = RepairOrderConcern::query()->create($attributes);
        $report->concernsImported++;

        return $concern;
    }

    /**
     * @param  array<string, mixed>  $legacyLine
     */
    private function importLine(
        RepairOrder $repairOrder,
        ?RepairOrderConcern $defaultConcern,
        array $legacyLine,
        LegacyImportOptions $options,
        LegacyImportReport $report,
    ): void {
        $legacyLineId = (int) ($legacyLine['id'] ?? 0);

        if ($legacyLineId <= 0) {
            $report->skip('Line missing id.');

            return;
        }

        $concern = $defaultConcern;

        if (isset($legacyLine['concern_id'])) {
            $mappedConcern = RepairOrderConcern::query()
                ->where('legacy_arksms_concern_id', (int) $legacyLine['concern_id'])
                ->where('repair_order_id', $repairOrder->id)
                ->first();

            $concern = $mappedConcern ?? $concern;
        }

        if (! $concern) {
            $report->skip("RO {$repairOrder->repair_order_id}: line {$legacyLineId} has no concern.");

            return;
        }

        $type = $this->mapper->mapLineType($legacyLine['type'] ?? null, $report, "Line {$legacyLineId}");
        $unitPrice = $legacyLine['unit_price'] ?? $legacyLine['sell'] ?? $legacyLine['price'] ?? 0;
        $quantity = (float) ($legacyLine['quantity'] ?? 1);

        if ($quantity <= 0) {
            $quantity = $type === RepairOrderLineType::Note ? 0 : 1;
        }

        $description = Str::limit(trim((string) ($legacyLine['description'] ?? 'Imported line')), 255, '');

        $subtotal = $this->mapper->dollarsToCents($legacyLine['subtotal'] ?? null);
        $tax = $this->mapper->dollarsToCents($legacyLine['tax'] ?? null);
        $shopFee = $this->mapper->dollarsToCents($legacyLine['shop_fee'] ?? null);
        $total = $this->mapper->dollarsToCents(
            $legacyLine['line_total'] ?? $legacyLine['total'] ?? $legacyLine['total_price'] ?? null,
        );

        if ($total === 0 && $subtotal === 0) {
            $subtotal = $this->mapper->dollarsToCents((float) $quantity * (float) $unitPrice);
            $total = $subtotal + $tax + $shopFee;
        } elseif ($total > 0 && $subtotal === 0) {
            $subtotal = max(0, $total - $tax - $shopFee);
        }

        $attributes = [
            'legacy_arksms_line_id' => $legacyLineId,
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $concern->id,
            'type' => $type->value,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price_cents' => $this->mapper->dollarsToCents($unitPrice),
            'part_cost_cents' => filled($legacyLine['part_cost'] ?? null)
                ? $this->mapper->dollarsToCents($legacyLine['part_cost'])
                : null,
            'subtotal_cents' => $subtotal,
            'tax_cents' => $tax,
            'shop_fee_cents' => $shopFee,
            'total_cents' => $total > 0 ? $total : ($subtotal + $tax + $shopFee),
            'vendor_name' => $legacyLine['vendor_name'] ?? null,
            'part_number' => $legacyLine['part_number'] ?? null,
            'is_overridden' => (bool) ($legacyLine['is_overridden'] ?? $legacyLine['price_overridden'] ?? true),
        ];

        if ($options->dryRun) {
            $report->linesImported++;

            return;
        }

        $existing = RepairOrderLine::query()->where('legacy_arksms_line_id', $legacyLineId)->first();

        if ($existing) {
            $existing->fill($attributes);
            $existing->save();
            $report->linesImported++;

            return;
        }

        RepairOrderLine::query()->create($attributes);
        $report->linesImported++;
    }

    private function importInvoices(
        RepairOrder $repairOrder,
        int $legacyRepairOrderId,
        LegacyImportOptions $options,
        LegacyImportReport $report,
    ): void {
        foreach ($this->reader->invoicesForRepairOrder($legacyRepairOrderId) as $legacyInvoice) {
            $legacyInvoiceId = (int) ($legacyInvoice['id'] ?? 0);

            if ($legacyInvoiceId <= 0) {
                continue;
            }

            $snapshot = [
                'schema_version' => 'legacy_import',
                'imported_at' => now()->toIso8601String(),
                'legacy_invoice_id' => $legacyInvoiceId,
                'repair_order_id' => $repairOrder->id,
                'totals' => [
                    'subtotal_cents' => $this->mapper->dollarsToCents($legacyInvoice['subtotal'] ?? 0),
                    'tax_cents' => $this->mapper->dollarsToCents($legacyInvoice['tax'] ?? 0),
                    'shop_fee_cents' => $this->mapper->dollarsToCents($legacyInvoice['shop_fee'] ?? 0),
                    'total_cents' => $this->mapper->dollarsToCents($legacyInvoice['total'] ?? 0),
                ],
            ];

            $documentNumber = (int) ($legacyInvoice['invoice_number'] ?? 0);

            if ($documentNumber <= 0) {
                $documentNumber = $legacyInvoiceId;
            }

            $attributes = [
                'legacy_arksms_invoice_id' => $legacyInvoiceId,
                'repair_order_id' => $repairOrder->id,
                'document_number' => $documentNumber,
                'snapshot_json' => $snapshot,
                'status' => 'final',
                'generated_at' => $this->parseTimestamp($legacyInvoice['generated_at'] ?? null) ?? now(),
            ];

            if ($options->dryRun) {
                $report->invoicesImported++;

                continue;
            }

            $existing = EstimateDocument::query()->where('legacy_arksms_invoice_id', $legacyInvoiceId)->first();

            if ($existing) {
                $existing->fill($attributes);
                $existing->save();
                $report->invoicesImported++;

                continue;
            }

            if (EstimateDocument::query()->where('repair_order_id', $repairOrder->id)->exists()) {
                $report->addWarning("RO {$repairOrder->repair_order_id}: skipped invoice {$legacyInvoiceId}; V2 already has an estimate document for this repair order.");

                continue;
            }

            EstimateDocument::query()->create($attributes);
            $report->invoicesImported++;
        }
    }

    private function countRepairOrderChildren(int $legacyRepairOrderId, LegacyImportReport $report): void
    {
        foreach ($this->reader->concernsForRepairOrder($legacyRepairOrderId) as $_) {
            $report->concernsImported++;
        }

        foreach ($this->reader->linesForRepairOrder($legacyRepairOrderId) as $_) {
            $report->linesImported++;
        }

        foreach ($this->reader->invoicesForRepairOrder($legacyRepairOrderId) as $_) {
            $report->invoicesImported++;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $legacy
     */
    private function persistModel(
        ?object $existing,
        string $modelClass,
        array $attributes,
        array $legacy,
        LegacyImportReport $report,
        string $counter,
    ): ?object {
        $timestamps = [
            'created_at' => $this->parseTimestamp($legacy['created_at'] ?? null),
            'updated_at' => $this->parseTimestamp($legacy['updated_at'] ?? null),
        ];

        if ($existing) {
            $report->duplicateMatches[] = "{$counter} legacy {$attributes[$this->legacyKeyFor($counter)]}: updated existing V2 #{$existing->id}";

            $existing->fill($attributes);

            foreach ($timestamps as $key => $value) {
                if ($value !== null) {
                    $existing->{$key} = $value;
                }
            }

            $existing->save();

            $this->incrementCounter($report, $counter);

            return $existing;
        }

        $model = new $modelClass($attributes);

        foreach ($timestamps as $key => $value) {
            if ($value !== null) {
                $model->{$key} = $value;
            }
        }

        $model->save();
        $this->incrementCounter($report, $counter);

        return $model;
    }

    private function legacyKeyFor(string $counter): string
    {
        return match ($counter) {
            'customers' => 'legacy_arksms_customer_id',
            'vehicles' => 'legacy_arksms_vehicle_id',
            'repair_orders' => 'repair_order_id',
            default => 'legacy_arksms_customer_id',
        };
    }

    private function incrementCounter(LegacyImportReport $report, string $counter): void
    {
        match ($counter) {
            'customers' => $report->customersImported++,
            'vehicles' => $report->vehiclesImported++,
            'repair_orders' => $report->repairOrdersImported++,
            default => null,
        };
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function safeColumnNames(string $table): array
    {
        try {
            return $this->reader->columnNames($table);
        } catch (\Throwable) {
            return [];
        }
    }

    public function persistReport(LegacyImportReport $report): void
    {
        Storage::disk('local')->makeDirectory('imports/ark-sms');
        Storage::disk('local')->put(
            config('legacy-arksms-import.report_path'),
            json_encode($report->toArray(), JSON_PRETTY_PRINT),
        );
    }
}
