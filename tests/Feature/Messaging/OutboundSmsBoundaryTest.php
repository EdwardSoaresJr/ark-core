<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\NotConfiguredOutboundSmsTransport;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Messaging\SendOutboundMessageAction;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

test('stock core leaves outbound sms unconfigured', function () {
    Http::fake();

    $transport = app(OutboundSmsTransport::class);

    expect($transport)->toBeInstanceOf(NotConfiguredOutboundSmsTransport::class)
        ->and($transport->isConfigured())->toBeFalse();

    expect(fn () => $transport->send('+17195550100', 'Hello'))
        ->toThrow(RuntimeException::class, 'Outbound SMS is not configured.');

    Http::assertNothingSent();
});

test('send outbound message action throws when texting is not configured', function () {
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
