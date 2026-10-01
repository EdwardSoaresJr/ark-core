<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Messaging\ShopAddressSmsCopy;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
        'shop_name' => 'Demo Auto Repair',
        'address_line_1' => '100 Main Street',
        'address_line_2' => 'Unit D',
        'city' => 'Demo City',
        'state' => 'CO',
        'postal_code' => '80909',
    ]);
    ShopSettings::forgetCurrent();
});

function shopAddressCustomer(string $phone = '7195551212'): Customer
{
    $customer = Customer::query()->create([
        'first_name' => 'Sarah',
        'last_name' => 'Customer',
        'phone' => $phone,
        'sms_consent_status' => CustomerSmsConsentStatus::Subscribed,
    ]);

    PhoneSmsCapability::query()->create([
        'normalized_phone' => PhoneNumber::normalize((string) $customer->phone),
        'valid' => true,
        'line_type' => 'mobile',
        'carrier_name' => 'Test',
        'sms_capable' => true,
        'reason' => null,
        'checked_at' => now(),
        'raw_payload' => ['source' => 'test'],
    ]);

    return $customer;
}

test('shop address sms copy includes street and maps link', function () {
    $body = ShopAddressSmsCopy::body();

    expect($body)->toContain('Demo Auto Repair')
        ->and($body)->toContain('100 Main Street')
        ->and($body)->toContain('Unit D')
        ->and($body)->toContain('Demo City, CO 80909')
        ->and($body)->toContain('Google Maps:')
        ->and($body)->toContain('maps.google.com/?q=');
});

test('shop address sms copy fails when street is missing', function () {
    ShopSettings::current()->update([
        'address_line_1' => null,
        'address_line_2' => null,
    ]);

    expect(fn () => ShopAddressSmsCopy::body())
        ->toThrow(RuntimeException::class, 'Shop address is not configured in Settings.');
});

test('send address creates outbound conversation message', function () {
    bindFakeOutboundSms();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = shopAddressCustomer();

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-actions.send-address', $customer));

    $response->assertOk()
        ->assertJsonStructure(['html', 'message_id', 'deliveries']);

    $message = ConversationMessage::query()->sole();

    expect($message->channel)->toBe(OperationalCommunicationChannel::Sms)
        ->and($message->direction)->toBe(OperationalCommunicationDirection::Outbound)
        ->and($message->body)->toContain('Demo Auto Repair')
        ->and($message->body)->toContain('100 Main Street')
        ->and($message->body)->toContain('maps.google.com')
        ->and($message->participant->participant_type)->toBe(ConversationParticipantType::Advisor);
});

test('send address can attach repair order context', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMaddress02',
            'status' => 'queued',
        ], 201),
    ]);
    bindFakeOutboundSms();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = shopAddressCustomer();
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'repair_order_id' => 9101,
        'concern_summary' => 'Directions',
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-actions.send-address', $customer), [
            'repair_order_id' => $repairOrder->id,
        ])
        ->assertOk();

    $message = ConversationMessage::query()->sole();

    expect($message->metadata['repair_order_id'] ?? null)->toBe($repairOrder->id);
});

test('send address rejects repair order for another customer', function () {
    Http::fake();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = shopAddressCustomer();
    $other = shopAddressCustomer('7195559998');
    $vehicle = Vehicle::query()->create([
        'customer_id' => $other->id,
        'year' => 2019,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $other->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'repair_order_id' => 9102,
        'concern_summary' => 'Nope',
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-actions.send-address', $customer), [
            'repair_order_id' => $repairOrder->id,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Repair order does not belong to this customer.');

    expect(ConversationMessage::query()->count())->toBe(0);
});

test('send address fails when shop address missing', function () {
    Http::fake();

    ShopSettings::current()->update([
        'address_line_1' => null,
        'address_line_2' => null,
    ]);
    ShopSettings::forgetCurrent();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = shopAddressCustomer();

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-actions.send-address', $customer))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Shop address is not configured in Settings.');
});
