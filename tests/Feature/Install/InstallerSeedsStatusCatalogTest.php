<?php

use App\Ark\Install\CompleteInstallationAction;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusDefinition;

it('first-run install seeds the repair order status catalog', function () {
    expect(RepairOrderStatusDefinition::query()->count())->toBe(0)
        ->and(app(RepairOrderStatusCatalog::class)->isBooted())->toBeFalse();

    $action = app(CompleteInstallationAction::class);
    $method = new ReflectionMethod(CompleteInstallationAction::class, 'seedOperationalCatalogs');
    $method->invoke($action);

    app(RepairOrderStatusCatalog::class)->forgetCache();

    expect(RepairOrderStatusDefinition::query()->count())->toBeGreaterThan(0)
        ->and(app(RepairOrderStatusCatalog::class)->isBooted())->toBeTrue();
});
