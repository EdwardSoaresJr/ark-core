<?php

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;

it('maps each chip tone to a Tabler color that matches the status badge family', function (string $tone, string $color): void {
    expect(RepairOrderStatusColor::tablerColorForChipTone($tone))->toBe($color)
        ->and(RepairOrderStatusColor::edgeClass($tone))->toBe('ark-status-edge ark-status-edge--'.$tone)
        ->and(RepairOrderStatusColor::normalizeChipTone($tone))->toBe($tone);
})->with([
    'approval' => ['approval', 'orange'],
    'blocked' => ['blocked', 'yellow'],
    'motion' => ['motion', 'azure'],
    'ready' => ['ready', 'green'],
    'closed' => ['closed', 'dark'],
    'move' => ['move', 'secondary'],
]);

it('normalizes unknown tones to the neutral move edge', function (): void {
    expect(RepairOrderStatusColor::normalizeChipTone('not-a-tone'))->toBe('move')
        ->and(RepairOrderStatusColor::edgeClass(null))->toBe('ark-status-edge ark-status-edge--move')
        ->and(RepairOrderStatusColor::tablerColorForChipTone('bogus'))->toBe('secondary');
});
