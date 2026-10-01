<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Messaging\ResolvePhoneSmsCapabilityAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);
});

test('send estimate reaches transport when a 10-digit phone has no capability row', function () {
    $transport = bindFakeOutboundSms();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = unknownCapabilityRepairOrder('7195550349');

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder))
        ->assertOk();

    expect($transport->sent)->toHaveCount(1)
        ->and($transport->sent[0]['to'])->toBe('7195550349')
        ->and(PhoneSmsCapability::query()->count())->toBe(0);
});

test('customer sms reaches transport when a 10-digit phone has no capability row', function () {
    $transport = bindFakeOutboundSms();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = unknownCapabilityCustomer('7195558303');

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Your car is ready.',
        ])
        ->assertOk()
        ->assertJsonPath('message.body', 'Your car is ready.');

    expect($transport->sent)->toHaveCount(1)
        ->and($transport->sent[0]['to'])->toBe('7195558303')
        ->and(PhoneSmsCapability::query()->count())->toBe(0);
});

test('known sms incapable numbers stay blocked and keep their reason', function () {
    $transport = bindFakeOutboundSms();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = unknownCapabilityCustomer('7195555556');
    $reason = 'Landline (CenturyLink) - cannot receive SMS.';

    PhoneSmsCapability::query()->create([
        'normalized_phone' => '7195555556',
        'valid' => true,
        'line_type' => 'landline',
        'carrier_name' => 'CenturyLink',
        'sms_capable' => false,
        'reason' => $reason,
        'checked_at' => now(),
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Your car is ready.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', $reason);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', unknownCapabilityRepairOrder('7195555556', $customer)))
        ->assertStatus(422)
        ->assertJsonPath('message', $reason);

    $stored = PhoneSmsCapability::findByNormalizedPhone('7195555556');

    expect($transport->sent)->toBe([])
        ->and($stored?->sms_capable)->toBeFalse()
        ->and($stored?->reason)->toBe($reason)
        ->and(PhoneSmsCapability::query()->count())->toBe(1);
});

test('incomplete phone numbers stay blocked before transport', function (string $phone) {
    $transport = bindFakeOutboundSms();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = unknownCapabilityCustomer($phone);

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Your car is ready.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The phone number on file is incomplete or invalid.');

    expect($transport->sent)->toBe([])
        ->and(PhoneSmsCapability::query()->count())->toBe(0);
})->with([
    '1 digit' => '7',
    '4 digits' => '0107',
    '8 digits' => '55551234',
]);

test('a missing phone is blocked before transport', function () {
    $transport = bindFakeOutboundSms();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = unknownCapabilityCustomer(null);

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Your car is ready.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Customer does not have a phone number on file.');

    expect(fn () => app(ResolvePhoneSmsCapabilityAction::class)->assertCapableOrFail(''))
        ->toThrow(RuntimeException::class, 'The phone number on file is incomplete or invalid.')
        ->and($transport->sent)->toBe([])
        ->and(PhoneSmsCapability::query()->count())->toBe(0);
});

test('a stored capable row still allows the send', function () {
    $transport = bindFakeOutboundSms();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = unknownCapabilityCustomer('7195556289');

    PhoneSmsCapability::query()->create([
        'normalized_phone' => '7195556289',
        'valid' => true,
        'line_type' => 'mobile',
        'sms_capable' => true,
        'reason' => null,
        'checked_at' => now(),
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'body' => 'Your car is ready.',
        ])
        ->assertOk();

    expect($transport->sent)->toHaveCount(1)
        ->and(PhoneSmsCapability::query()->count())->toBe(1)
        ->and(PhoneSmsCapability::findByNormalizedPhone('7195556289')?->sms_capable)->toBeTrue();
});

function unknownCapabilityCustomer(?string $phone): Customer
{
    return Customer::query()->create([
        'first_name' => 'Unknown',
        'last_name' => 'Capability',
        'phone' => $phone,
        'customer_type' => 'Retail',
    ]);
}

function unknownCapabilityRepairOrder(string $phone, ?Customer $customer = null): RepairOrder
{
    $customer ??= unknownCapabilityCustomer($phone);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
        'vin' => '1HGCM82633A004352',
        'normalized_vin' => '1HGCM82633A004352',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 8801,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brakes',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace front pads',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
        'position' => 1,
    ]);

    return $repairOrder;
}
