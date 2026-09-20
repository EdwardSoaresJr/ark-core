<?php

use App\Ark\Mobile\Push\MobileAwarePushCopy;
use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Vehicles\Vehicle;


test('lifecycle push copy uses customer name and operational body', function (): void {
    $copy = app(MobileAwarePushCopy::class);

    $customer = Customer::query()->create([
        'first_name' => 'John',
        'last_name' => 'Smith',
        'phone' => '7195554411',
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
        'status' => 'estimate',
        'concern_summary' => 'Brake estimate',
    ]);

    $approval = ApprovalEvent::query()->create([
        'visit_id' => $repairOrder->id,
        'estimate_snapshot_reference' => 'test-snapshot',
        'approval_type' => ApprovalType::Repair,
        'approved_amount_cents' => 12000,
        'source' => ApprovalSource::Portal,
        'approved_by' => 'John Smith',
        'approved_at' => now(),
    ]);

    $approved = $copy->forEstimateApproved($approval);
    expect($approved['title'])->toBe('John Smith')
        ->and($approved['body'])->toContain('Brake estimate approved');

    $viewed = $copy->forEstimateViewed($repairOrder);
    expect($viewed['title'])->toBe('John Smith')
        ->and($viewed['body'])->toContain('Opened the estimate');

    $parts = $copy->forPartsArrived($repairOrder, 'Water pump');
    expect($parts['body'])->toContain('Water pump arrived');

    $session = CallSession::query()->create([
        'customer_id' => $customer->id,
        'repair_order_id' => $repairOrder->id,
        'status' => CallSessionStatus::Missed,
        'from_number' => '7195554411',
        'to_number' => '7195559999',
        'normalized_from' => '7195554411',
        'normalized_to' => '7195559999',
        'provider' => 'twilio',
        'provider_call_sid' => 'CA-lifecycle-copy',
        'direction' => CallSessionDirection::Inbound,
    ]);

    $missed = $copy->forMissedCall($session);
    expect($missed['title'])->toBe('John Smith')
        ->and($missed['body'])->toContain('Missed your call');
});
