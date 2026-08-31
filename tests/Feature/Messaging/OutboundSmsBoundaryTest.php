<?php

use App\Ark\Operations\Messaging\NotConfiguredOutboundSmsTransport;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Messaging\SendOutboundMessageAction;
use App\Ark\Operations\Customers\Customer;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use App\Ark\Runtime\Authorization\ArkRole;

test('stock core binds not-configured outbound sms transport', function () {
    $transport = app(OutboundSmsTransport::class);

    expect($transport)->toBeInstanceOf(NotConfiguredOutboundSmsTransport::class)
        ->and($transport->isConfigured())->toBeFalse();
});

test('not-configured outbound sms transport throws on send', function () {
    $transport = app(OutboundSmsTransport::class);

    expect(fn () => $transport->send('+17195550100', 'Hello'))
        ->toThrow(RuntimeException::class, 'Outbound SMS is not configured.');
});

test('send outbound message action throws when messaging is not configured', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Bound',
        'last_name' => 'ary',
        'phone' => '7195550100',
        'customer_type' => 'Retail',
    ]);

    expect(fn () => app(SendOutboundMessageAction::class)->execute(
        customer: $customer,
        actor: $user,
        body: 'Hello from the shop',
    ))->toThrow(RuntimeException::class, 'Outbound SMS is not configured.');
});
