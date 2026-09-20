<?php

use App\Ark\Import\LegacyRepairOrderTimeline;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;

// RepairOrderStatus::isTerminal() resolves the status catalog from the container.

test('legacy repair order timeline prefers status log close over updated_at', function () {
    $closedAt = LegacyRepairOrderTimeline::closedAt([
        'status' => 'closed',
        'legacy_closed_at' => '2026-04-24 11:39:55',
        'updated_at' => '2026-06-03 15:05:25',
        'status_changed_at' => null,
    ], RepairOrderStatus::Closed, 'closed');

    expect($closedAt?->toDateTimeString())->toBe('2026-04-24 11:39:55');
});

test('legacy repair order timeline leaves close date null for active workflow', function () {
    expect(LegacyRepairOrderTimeline::closedAt([
        'status' => 'in_progress',
        'updated_at' => '2026-06-03 15:05:25',
    ], RepairOrderStatus::InProgress, 'in_progress'))->toBeNull();
});

test('legacy repair order opened_at uses created_at', function () {
    expect(LegacyRepairOrderTimeline::openedAt([
        'created_at' => '2026-04-23 15:54:13',
    ])?->toDateTimeString())->toBe('2026-04-23 15:54:13');
});
