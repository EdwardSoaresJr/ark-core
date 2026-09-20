<?php

use App\Ark\Operations\Messaging\NotConfiguredOutboundSmsTransport;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Messaging\SendOutboundMessageAction;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Texting\PlatformOutboundSmsTransport;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use App\Ark\Runtime\Authorization\ArkRole;
use Illuminate\Support\Facades\Http;

test('stock core binds platform outbound sms transport', function () {
    $transport = app(OutboundSmsTransport::class);

    expect($transport)->toBeInstanceOf(PlatformOutboundSmsTransport::class)
        ->and($transport)->not->toBeInstanceOf(NotConfiguredOutboundSmsTransport::class)
        ->and($transport->isConfigured())->toBeFalse();
});

test('platform outbound sms transport throws when platform is not connected', function () {
    $transport = app(OutboundSmsTransport::class);

    expect(fn () => $transport->send('+17195550100', 'Hello'))
        ->toThrow(RuntimeException::class, 'Outbound SMS is not configured.');
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

test('platform outbound sms transport sends conversation.send through platform', function () {
    \App\Ark\Operations\Settings\ShopSettings::current()->persistTrusted([
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://platform.test',
        'cloud_credential' => 'test-credential-secret',
        'cloud_shop_public_id' => (string) \Illuminate\Support\Str::uuid(),
        'ark_mail_status' => 'connected',
        'ark_mail_credential' => 'test-credential-secret',
    ]);

    \App\Ark\Install\InstallationIdentity::write((string) \Illuminate\Support\Str::uuid());

    Http::fake([
        'https://platform.test/api/v1/services/sms/messages/conversation' => Http::response([
            'ok' => true,
            'status' => 'provider_sent',
            'message_id' => 'msg-public-1',
            'provider_message_id' => 'SMfake001',
            'correlation_id' => 'corr-1',
        ], 200),
    ]);

    $result = app(OutboundSmsTransport::class)->send('+17195550100', 'Hello Molly');

    expect($result->messageId)->toBe('SMfake001')
        ->and($result->status)->toBe('provider_sent');

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($request->url(), '/api/v1/services/sms/messages/conversation')
            && ($data['operation'] ?? null) === 'conversation.send'
            && ($data['to'] ?? null) === '+17195550100'
            && ($data['body'] ?? null) === 'Hello Molly'
            && filled($data['idempotency_key'] ?? null);
    });
});
