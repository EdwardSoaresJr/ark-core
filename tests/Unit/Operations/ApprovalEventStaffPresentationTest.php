<?php

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalEventStaffPresentation;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;

test('staff approval headline reflects deferred portal response', function () {
    $event = new ApprovalEvent([
        'approval_type' => ApprovalType::Partial,
        'approved_amount_cents' => 0,
        'source' => ApprovalSource::Portal,
    ]);

    expect(ApprovalEventStaffPresentation::headline($event))
        ->toBe('Customer deferred recommended work');
});

test('staff approval headline reflects paid authorization', function () {
    $event = new ApprovalEvent([
        'approval_type' => ApprovalType::Repair,
        'approved_amount_cents' => 15593,
        'source' => ApprovalSource::Portal,
    ]);

    expect(ApprovalEventStaffPresentation::headline($event))
        ->toBe('Repair authorization');
});

test('a zero-amount diagnostic or repair event is not presented as a deferral', function (ApprovalType $type) {
    $event = new ApprovalEvent([
        'approval_type' => $type,
        'approved_amount_cents' => 0,
        'source' => ApprovalSource::InPerson,
    ]);

    expect(ApprovalEventStaffPresentation::isDeferredResponse($event))->toBeFalse()
        ->and(ApprovalEventStaffPresentation::headline($event))->toBe($type->label().' authorization')
        ->and(ApprovalEventStaffPresentation::amountLabel($event))->toBe('Amount not recorded');
})->with([ApprovalType::Diagnostic, ApprovalType::Repair]);

test('each event presents its own recorded amount', function () {
    $first = new ApprovalEvent(['approval_type' => ApprovalType::Repair, 'approved_amount_cents' => 21500, 'source' => ApprovalSource::InPerson]);
    $supplemental = new ApprovalEvent(['approval_type' => ApprovalType::Repair, 'approved_amount_cents' => 29750, 'source' => ApprovalSource::Phone]);
    $deferred = new ApprovalEvent(['approval_type' => ApprovalType::Partial, 'approved_amount_cents' => 0, 'source' => ApprovalSource::Portal]);

    expect(ApprovalEventStaffPresentation::recordedAmountCents($first))->toBe(21500)
        ->and(ApprovalEventStaffPresentation::recordedAmountCents($supplemental))->toBe(29750)
        ->and(ApprovalEventStaffPresentation::amountLabel($first))->toBe('Approved amount')
        ->and(ApprovalEventStaffPresentation::amountLabel($deferred))->toBe('No approved work');
});
