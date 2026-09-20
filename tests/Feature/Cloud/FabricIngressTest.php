<?php

use App\Ark\Platform\PlatformConnection;
use App\Ark\Platform\Http\VerifyPlatformFabricSignature;
use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Communications\Events\CommsInterruptReceived;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\Events\IncomingCallReceived;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');

    InstallationIdentity::write((string) Str::uuid());
    ShopSettings::current()->persistTrusted([
        'shop_name' => 'Casey Auto Repair',
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_shop_public_id' => (string) Str::uuid(),
        'cloud_credential' => 'test-credential-32-characters-min!!',
        'ark_mail_status' => 'connected',
    ]);
});

/**
 * @return array{0: string, 1: array<string, string>}
 */
function fabricSignedRequest(array $body, ?string $installationId = null, ?string $nonce = null, ?string $credential = null): array
{
    $raw = json_encode($body, JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $nonce ??= Str::random(24);
    $installationId ??= InstallationIdentity::uuid();
    $credential ??= (string) PlatformConnection::current()->credential();

    $signature = hash_hmac('sha256', implode("\n", [
        $timestamp,
        $nonce,
        'POST',
        VerifyPlatformFabricSignature::PATH,
        hash('sha256', $raw),
    ]), $credential);

    return [$raw, [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_ARK_INSTALLATION_ID' => $installationId,
        'HTTP_X_ARK_TIMESTAMP' => $timestamp,
        'HTTP_X_ARK_NONCE' => $nonce,
        'HTTP_X_ARK_SIGNATURE' => $signature,
    ]];
}

test('fabric ingress rejects missing signature', function () {
    $body = [
        'operation' => 'voice.incoming.started',
        'installation_id' => InstallationIdentity::uuid(),
        'payload' => [
            'interrupt' => [
                'call_session_id' => -1,
                'display_phone' => '(719) 555-0100',
                'kind' => 'call',
            ],
        ],
    ];

    $this->call(
        'POST',
        '/webhooks/cloud/fabric/events',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ARK_INSTALLATION_ID' => InstallationIdentity::uuid(),
            'HTTP_X_ARK_TIMESTAMP' => (string) time(),
            'HTTP_X_ARK_NONCE' => Str::random(24),
        ],
        json_encode($body, JSON_THROW_ON_ERROR),
    )->assertUnauthorized();
});

test('fabric ingress rejects wrong installation', function () {
    $body = [
        'operation' => 'voice.incoming.started',
        'installation_id' => InstallationIdentity::uuid(),
        'payload' => [
            'interrupt' => [
                'call_session_id' => -1,
                'display_phone' => '(719) 555-0100',
                'kind' => 'call',
            ],
        ],
    ];

    [$raw, $server] = fabricSignedRequest($body, installationId: (string) Str::uuid());

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertUnauthorized();
});

test('fabric ingress rejects replay nonce', function () {
    $body = [
        'operation' => 'voice.incoming.started',
        'installation_id' => InstallationIdentity::uuid(),
        'occurred_at' => now()->toIso8601String(),
        'payload' => [
            'interrupt' => [
                'call_session_id' => -42,
                'display_phone' => '(719) 555-0100',
                'kind' => 'call',
            ],
        ],
    ];

    $nonce = Str::random(24);
    [$raw, $server] = fabricSignedRequest($body, nonce: $nonce);

    Event::fake([IncomingCallReceived::class, CommsInterruptReceived::class]);

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertOk();

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertUnauthorized();
});

test('fabric ingress accepts voice.incoming.started and broadcasts interrupts', function () {
    Event::fake([IncomingCallReceived::class, CommsInterruptReceived::class]);

    $body = [
        'operation' => 'voice.incoming.started',
        'installation_id' => InstallationIdentity::uuid(),
        'occurred_at' => now()->toIso8601String(),
        'payload' => [
            'interrupt' => [
                'call_session_id' => -7,
                'display_phone' => '(719) 555-0142',
                'kind' => 'call',
                'matched' => false,
                'customer_name' => null,
            ],
        ],
    ];

    [$raw, $server] = fabricSignedRequest($body);

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertOk()
        ->assertJson(['ok' => true]);

    Event::assertDispatched(IncomingCallReceived::class, function (IncomingCallReceived $event): bool {
        return (int) ($event->context['call_session_id'] ?? 0) === -7
            && ($event->context['display_phone'] ?? null) === '(719) 555-0142'
            && ($event->context['kind'] ?? null) === 'call';
    });

    Event::assertDispatched(CommsInterruptReceived::class, function (CommsInterruptReceived $event): bool {
        return ($event->payload['kind'] ?? null) === 'call'
            && ($event->payload['action'] ?? null) === 'show'
            && (int) ($event->payload['interrupt']['call_session_id'] ?? 0) === -7;
    });
});

test('fabric ingress rejects unknown operation', function () {
    $body = [
        'operation' => 'voice.unknown.event',
        'installation_id' => InstallationIdentity::uuid(),
        'payload' => [],
    ];

    [$raw, $server] = fabricSignedRequest($body);

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertStatus(422)
        ->assertJson(['ok' => false, 'error' => 'unknown_operation']);
});

test('fabric ingress rejects when cloud not connected', function () {
    PlatformConnection::current()->clear();
    Cache::flush();

    $body = [
        'operation' => 'voice.incoming.started',
        'installation_id' => InstallationIdentity::uuid(),
        'payload' => [
            'interrupt' => [
                'call_session_id' => -1,
                'display_phone' => '(719) 555-0100',
                'kind' => 'call',
            ],
        ],
    ];

    $raw = json_encode($body, JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $nonce = Str::random(24);
    $signature = hash_hmac('sha256', implode("\n", [
        $timestamp,
        $nonce,
        'POST',
        VerifyPlatformFabricSignature::PATH,
        hash('sha256', $raw),
    ]), 'stale-credential-after-disconnect!!!!');

    $this->call(
        'POST',
        '/webhooks/cloud/fabric/events',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ARK_INSTALLATION_ID' => InstallationIdentity::uuid(),
            'HTTP_X_ARK_TIMESTAMP' => $timestamp,
            'HTTP_X_ARK_NONCE' => $nonce,
            'HTTP_X_ARK_SIGNATURE' => $signature,
        ],
        $raw,
    )->assertUnauthorized();
});

test('fabric sms.incoming.received persists conversation message then interrupts', function () {
    Event::fake([CommsInterruptReceived::class]);

    $body = [
        'operation' => 'sms.incoming.received',
        'installation_id' => InstallationIdentity::uuid(),
        'occurred_at' => now()->toIso8601String(),
        'payload' => [
            'from_phone' => '+17195550199',
            'to_phone' => '+17195550100',
            'body' => 'Need an appointment tomorrow',
            'provider_message_id' => 'SMinbound-fabric-1',
            'media' => [],
            'opt_out' => false,
        ],
    ];

    [$raw, $server] = fabricSignedRequest($body);

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('ingested', true);

    $message = \App\Ark\Operations\Conversations\ConversationMessage::query()
        ->where('metadata->provider_message_id', 'SMinbound-fabric-1')
        ->first();

    expect($message)->not->toBeNull()
        ->and($message->body)->toBe('Need an appointment tomorrow');

    Event::assertDispatched(CommsInterruptReceived::class, function (CommsInterruptReceived $event) use ($message): bool {
        return ($event->payload['kind'] ?? null) === 'sms'
            && ($event->payload['action'] ?? null) === 'show'
            && (int) ($event->payload['interrupt']['conversation_message_id'] ?? 0) === (int) $message->id;
    });
});

test('fabric sms inbound is idempotent on provider message id', function () {
    Event::fake([CommsInterruptReceived::class]);

    $body = [
        'operation' => 'sms.incoming.received',
        'installation_id' => InstallationIdentity::uuid(),
        'payload' => [
            'from_phone' => '+17195550188',
            'to_phone' => '+17195550100',
            'body' => 'Ping',
            'provider_message_id' => 'SMinbound-dup-1',
        ],
    ];

    [$raw, $server] = fabricSignedRequest($body);
    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)->assertOk();

    [$raw2, $server2] = fabricSignedRequest($body);
    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server2, $raw2)
        ->assertOk()
        ->assertJsonPath('ingested', false);

    expect(\App\Ark\Operations\Conversations\ConversationMessage::query()
        ->where('metadata->provider_message_id', 'SMinbound-dup-1')
        ->count())->toBe(1);
});
