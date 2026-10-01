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
