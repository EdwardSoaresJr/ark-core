<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\SendOutboundMessengerAction;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('messenger outbound rejects with not configured in core', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Meta',
        'last_name' => 'Guest',
        'phone' => '555-0101',
        'messenger_psid' => 'psid-boundary-001',
    ]);

    expect(fn () => app(SendOutboundMessengerAction::class)->execute(
        customer: $customer,
        actor: $advisor,
        body: 'Hello from ARK',
    ))->toThrow(RuntimeException::class, 'Messenger outbound is not configured.');
});

test('messenger outbound api returns not configured', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $customer = Customer::query()->create([
        'first_name' => 'Meta',
        'last_name' => 'Guest',
        'phone' => '555-0102',
        'messenger_psid' => 'psid-boundary-002',
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.customers.conversation-messages.store', $customer), [
            'channel' => 'messenger',
            'body' => 'Hello',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Messenger outbound is not configured.');
});
