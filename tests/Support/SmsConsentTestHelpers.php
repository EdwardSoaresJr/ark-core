<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Vehicles\Vehicle;

function seedMobileSmsCapability(string $phone): PhoneSmsCapability
{
    return PhoneSmsCapability::query()->updateOrCreate(
        ['normalized_phone' => $phone],
        [
            'valid' => true,
            'line_type' => 'mobile',
            'carrier_name' => 'Test Carrier',
            'sms_capable' => true,
            'reason' => null,
            'checked_at' => now(),
            'raw_payload' => ['source' => 'test'],
        ],
    );
}

function smsConsentCustomer(
    string $first,
    string $last,
    string $phone,
    CustomerSmsConsentStatus $consent = CustomerSmsConsentStatus::Subscribed,
): Customer {
    return Customer::query()->create([
        'first_name' => $first,
        'last_name' => $last,
        'phone' => $phone,
        'customer_type' => 'Retail',
        'sms_consent_status' => $consent,
    ]);
}

function smsConsentRepairOrder(Customer $customer): RepairOrder
{
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 8801,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'SMS consent test',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    return $repairOrder;
}
