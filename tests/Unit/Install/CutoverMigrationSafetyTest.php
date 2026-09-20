<?php

use App\Ark\Install\DeferredMigrationLoader;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Support\Facades\Schema;

test('deferred website growth drop is omitted when preserve is on', function () {
    config(['ark.preserve_website_growth_schema' => true]);

    expect(DeferredMigrationLoader::paths())->toBe([]);
});

test('deferred website growth drop is loaded for fresh installs', function () {
    config(['ark.preserve_website_growth_schema' => false]);

    $paths = DeferredMigrationLoader::paths();

    expect($paths)->toHaveCount(1)
        ->and(is_file($paths[0].'/2026_09_05_140000_drop_website_growth_schema_from_core.php'))->toBeTrue();
});

test('repair order public id migration can run when the column already exists', function () {
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::Estimate);
    expect(Schema::hasColumn('repair_orders', 'public_id'))->toBeTrue();

    $migration = require database_path('migrations/2026_09_04_120000_add_public_id_to_repair_orders.php');
    $migration->up();

    expect(Schema::hasColumn('repair_orders', 'public_id'))->toBeTrue()
        ->and($repairOrder->fresh()->public_id)->not->toBeEmpty();
});

test('payment capture attempts migration can run when the table already exists', function () {
    expect(Schema::hasTable('payment_capture_attempts'))->toBeTrue();

    $before = Schema::getColumnListing('payment_capture_attempts');

    $migration = require database_path('migrations/2026_09_05_220000_create_payment_capture_attempts_table.php');
    $migration->up();

    expect(Schema::getColumnListing('payment_capture_attempts'))->toBe($before);
});
