<?php

use App\Ark\Import\LegacyInvoicePaymentBackfill;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\InvoiceSnapshotBuilder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('legacy invoice payment backfill resolves invoice snapshot builder from operations namespace', function () {
    $backfill = app(LegacyInvoicePaymentBackfill::class);

    expect($backfill)->toBeInstanceOf(LegacyInvoicePaymentBackfill::class);

    $reflection = new \ReflectionClass($backfill);
    $parameter = $reflection->getConstructor()?->getParameters()[4] ?? null;

    expect($parameter)->not->toBeNull()
        ->and($parameter->getType()?->getName())->toBe(InvoiceSnapshotBuilder::class);
});

test('legacy invoice payment backfill dry run reads legacy invoice payments', function () {
    $connection = 'legacy_invoice_backfill_test';

    config([
        "database.connections.{$connection}" => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ],
    ]);

    Schema::connection($connection)->create('repair_orders', function ($table): void {
        $table->id();
        $table->unsignedBigInteger('repair_order_id');
    });

    Schema::connection($connection)->create('invoices', function ($table): void {
        $table->id();
        $table->unsignedBigInteger('repair_order_id');
        $table->decimal('subtotal', 10, 2)->default(0);
        $table->decimal('tax_total', 10, 2)->default(0);
        $table->decimal('shop_fees_total', 10, 2)->default(0);
        $table->decimal('total', 10, 2);
        $table->timestamp('finalized_at')->nullable();
        $table->timestamp('created_at')->nullable();
    });

    Schema::connection($connection)->create('invoice_payments', function ($table): void {
        $table->id();
        $table->unsignedBigInteger('invoice_id');
        $table->decimal('amount', 10, 2);
        $table->string('method')->default('card');
        $table->string('reference')->nullable();
        $table->boolean('is_refund')->default(false);
        $table->timestamp('paid_at')->nullable();
        $table->timestamp('applied_at')->nullable();
    });

    DB::connection($connection)->table('repair_orders')->insert([
        'id' => 301,
        'repair_order_id' => 1301,
    ]);

    DB::connection($connection)->table('invoices')->insert([
        'id' => 501,
        'repair_order_id' => 301,
        'subtotal' => 450.00,
        'tax_total' => 36.00,
        'shop_fees_total' => 14.00,
        'total' => 500.00,
        'finalized_at' => '2024-06-10 15:00:00',
        'created_at' => '2024-06-10 14:00:00',
    ]);

    DB::connection($connection)->table('invoice_payments')->insert([
        'id' => 701,
        'invoice_id' => 501,
        'amount' => 500.00,
        'method' => 'card',
        'reference' => 'legacy-ref-701',
        'is_refund' => false,
        'paid_at' => '2024-06-10 15:30:00',
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Legacy',
        'last_name' => 'Payment',
        'phone' => '7195550101',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'repair_order_id' => 1301,
        'concern_summary' => 'Legacy payment backfill fixture.',
    ]);

    $result = app(LegacyInvoicePaymentBackfill::class)->backfillRepairOrder(
        $repairOrder,
        $connection,
        dryRun: true,
    );

    expect($result['payments_recorded'])->toBe(1)
        ->and($result['payments_skipped'])->toBe(0)
        ->and($result['write_off_cents'])->toBe(0)
        ->and($result['balance_due_cents'])->toBe(0)
        ->and($result['paid'])->toBeTrue();
});

test('backfill command dry run succeeds when legacy connection is available', function () {
    $connection = 'legacy_invoice_backfill_command_test';

    config([
        "database.connections.{$connection}" => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ],
    ]);

    Schema::connection($connection)->create('repair_orders', function ($table): void {
        $table->id();
        $table->unsignedBigInteger('repair_order_id');
    });

    Schema::connection($connection)->create('invoices', function ($table): void {
        $table->id();
        $table->unsignedBigInteger('repair_order_id');
        $table->decimal('total', 10, 2);
        $table->timestamp('finalized_at')->nullable();
        $table->timestamp('created_at')->nullable();
    });

    Schema::connection($connection)->create('invoice_payments', function ($table): void {
        $table->id();
        $table->unsignedBigInteger('invoice_id');
        $table->decimal('amount', 10, 2);
        $table->string('method')->default('card');
        $table->boolean('is_refund')->default(false);
        $table->timestamp('paid_at')->nullable();
    });

    DB::connection($connection)->table('repair_orders')->insert([
        'id' => 302,
        'repair_order_id' => 1302,
    ]);

    DB::connection($connection)->table('invoices')->insert([
        'id' => 502,
        'repair_order_id' => 302,
        'total' => 100.00,
        'finalized_at' => '2024-06-11 10:00:00',
        'created_at' => '2024-06-11 10:00:00',
    ]);

    DB::connection($connection)->table('invoice_payments')->insert([
        'id' => 702,
        'invoice_id' => 502,
        'amount' => 100.00,
        'method' => 'cash',
        'is_refund' => false,
        'paid_at' => '2024-06-11 10:05:00',
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Command',
        'last_name' => 'Fixture',
        'phone' => '7195550102',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Corolla',
    ]);

    RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'repair_order_id' => 1302,
        'concern_summary' => 'Command dry-run fixture.',
    ]);

    Artisan::call('ark:backfill-legacy-invoice-payments', [
        '--shop-ro' => 1302,
        '--legacy-connection' => $connection,
        '--dry-run' => true,
    ]);

    expect(Artisan::output())->toContain('Dry run only');
});
