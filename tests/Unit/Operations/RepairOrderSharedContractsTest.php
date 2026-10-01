<?php

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Support\Str;

test('ensurePublicId persists a uuid and keeps it stable', function () {
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::Estimate);
    $first = $repairOrder->ensurePublicId();

    expect($first)->not->toBe('')
        ->and(Str::isUuid($first))->toBeTrue()
        ->and($repairOrder->fresh()->ensurePublicId())->toBe($first);
});

test('repair order status answers is and enum', function () {
    expect(RepairOrderStatus::WaitingApproval->is(RepairOrderStatus::WaitingApproval))->toBeTrue()
        ->and(RepairOrderStatus::WaitingApproval->is('waiting_approval'))->toBeTrue()
        ->and(RepairOrderStatus::WaitingApproval->is(RepairOrderStatus::Estimate))->toBeFalse()
        ->and(RepairOrderStatus::InProgress->enum())->toBe(RepairOrderStatus::InProgress);
});

test('inspection and estimate portal routes accept token', function () {
    $token = str_repeat('a', 64);

    expect(route('portal.inspections.show', ['token' => $token]))->toContain('/portal/inspections/'.$token)
        ->and(route('portal.estimates.show', ['token' => $token]))->toContain('/portal/estimates/'.$token)
        ->and(route('portal.invoice-pay.show', ['token' => $token]))->toContain('/portal/pay/'.$token);
});
