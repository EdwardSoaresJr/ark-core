<?php

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkflowStatus;

test('workflow status isIntake matches draft and estimate only', function () {
    expect(RepairOrderWorkflowStatus::from(RepairOrderStatus::Draft)->isIntake())->toBeTrue()
        ->and(RepairOrderWorkflowStatus::from(RepairOrderStatus::Estimate)->isIntake())->toBeTrue()
        ->and(RepairOrderWorkflowStatus::from(RepairOrderStatus::WaitingApproval)->isIntake())->toBeFalse()
        ->and(RepairOrderWorkflowStatus::from('awaiting_approval')->isIntake())->toBeFalse();
});
