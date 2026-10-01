<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Communications\Events\CommsInterruptReceived;
use App\Ark\Operations\Communications\HostedSmsInterrupt;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_core_mirror', false);

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
 * @return array<string, mixed>
 */
function hostedSmsFabricBody(array $payloadOverrides = []): array
{
    return [
        'operation' => 'sms.incoming.received',
        'installation_id' => InstallationIdentity::uuid(),
        'occurred_at' => now()->toIso8601String(),
        'payload' => array_merge([
            'from_phone' => '+17195550199',
            'to_phone' => '+17195550100',
            'body' => 'Need an appointment tomorrow',
            'provider_message_id' => 'SMinbound-hosted-1',
            'message_public_id' => '8f3c1d2a-1111-2222-3333-444444444444',
            'conversation_public_id' => '9a4d2e3b-5555-6666-7777-888888888888',
            'media' => [],
            'opt_out' => false,
        ], $payloadOverrides),
    ];
}

test('hosted inbound SMS with mirroring disabled broadcasts an unread popup payload', function () {
    Event::fake([CommsInterruptReceived::class]);

    $body = hostedSmsFabricBody();
    [$raw, $server] = fabricSignedRequest($body);

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('mirrored', false)
        ->assertJsonPath('interrupt', true);

    expect(ConversationMessage::query()->count())->toBe(0);

    $conversation = Conversation::query()->sole();

    Event::assertDispatched(CommsInterruptReceived::class, function (CommsInterruptReceived $event) use ($conversation): bool {
        $interrupt = $event->payload['interrupt'] ?? [];

        return ($event->payload['kind'] ?? null) === 'sms'
            && ($event->payload['action'] ?? null) === 'show'
            && ($interrupt['state'] ?? null) === 'unread'
            && ($interrupt['direction'] ?? null) === 'inbound'
            && ! array_key_exists('conversation_message_id', $interrupt)
            && ($interrupt['platform_message_public_id'] ?? null) === '8f3c1d2a-1111-2222-3333-444444444444'
            && (int) ($interrupt['conversation_id'] ?? 0) === (int) $conversation->id
            && ($interrupt['reply_url'] ?? '') === route('operations.conversations.reply', $conversation).'?compose=text#conversation-composer'
            && ($interrupt['mark_read_url'] ?? '') === route('operations.conversations.read', $conversation)
            && $event->payload['interrupt_key'] === 'message:platform:8f3c1d2a-1111-2222-3333-444444444444';
    });
});

test('mirrored inbound SMS still broadcasts a Core message interrupt', function () {
    config()->set('services.ark_platform.communications_core_mirror', true);
    Event::fake([CommsInterruptReceived::class]);

    $body = hostedSmsFabricBody([
        'provider_message_id' => 'SMinbound-mirrored-1',
        'from_phone' => '+17195550177',
    ]);
    [$raw, $server] = fabricSignedRequest($body);

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('ingested', true);

    $message = ConversationMessage::query()
        ->where('metadata->provider_message_id', 'SMinbound-mirrored-1')
        ->first();

    expect($message)->not->toBeNull();

    Event::assertDispatched(CommsInterruptReceived::class, function (CommsInterruptReceived $event) use ($message): bool {
        $interrupt = $event->payload['interrupt'] ?? [];

        return ($event->payload['kind'] ?? null) === 'sms'
            && ($event->payload['action'] ?? null) === 'show'
            && ($interrupt['state'] ?? null) === 'unread'
            && (int) ($interrupt['conversation_message_id'] ?? 0) === (int) $message->id
            && (int) ($interrupt['conversation_id'] ?? 0) === (int) $message->conversation_id;
    });
});

test('outbound SMS delivery updates do not raise an inbound popup', function () {
    Event::fake([CommsInterruptReceived::class]);

    $body = [
        'operation' => 'sms.delivery.updated',
        'installation_id' => InstallationIdentity::uuid(),
        'payload' => [
            'provider_message_id' => 'SMoutbound-1',
            'delivery_status' => 'delivered',
            'message_public_id' => 'aaaa1111-2222-3333-4444-555555555555',
        ],
    ];
    [$raw, $server] = fabricSignedRequest($body);

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertOk();

    Event::assertNotDispatched(CommsInterruptReceived::class);
});

test('duplicate hosted provider events share one interrupt identity', function () {
    Event::fake([CommsInterruptReceived::class]);

    $body = hostedSmsFabricBody(['provider_message_id' => 'SMinbound-dup-hosted']);
    [$raw, $server] = fabricSignedRequest($body);
    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)->assertOk();

    [$raw2, $server2] = fabricSignedRequest($body);
    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server2, $raw2)->assertOk();

    $keys = [];
    Event::assertDispatched(CommsInterruptReceived::class, function (CommsInterruptReceived $event) use (&$keys): bool {
        $keys[] = $event->payload['interrupt_key'] ?? '';

        return ($event->payload['interrupt']['platform_message_public_id'] ?? null) === '8f3c1d2a-1111-2222-3333-444444444444';
    });

    expect($keys)->toHaveCount(2)
        ->and(array_unique($keys))->toHaveCount(1)
        ->and($keys[0])->toBe('message:platform:8f3c1d2a-1111-2222-3333-444444444444');
});

test('platform public id is used when the Core message id is absent', function () {
    $interrupt = HostedSmsInterrupt::fromPlatformInbound(
        payload: [
            'message_public_id' => 'plat-msg-no-core',
            'conversation_public_id' => 'plat-convo-no-core',
        ],
        fromPhone: '+17195550199',
        body: 'Hi',
        hasMedia: false,
        conversationId: 12,
        customerId: null,
        customerName: null,
    );

    expect($interrupt)->not->toBeNull()
        ->and($interrupt)->not->toHaveKey('conversation_message_id')
        ->and($interrupt['platform_message_public_id'])->toBe('plat-msg-no-core')
        ->and($interrupt['state'])->toBe('unread')
        ->and($interrupt['conversation_id'])->toBe(12);
});

test('hosted popup navigation opens the Core conversation and customer hub when matched', function () {
    Event::fake([CommsInterruptReceived::class]);

    $customer = Customer::query()->create([
        'first_name' => 'Avairee',
        'last_name' => 'Foote',
        'phone' => '7195550199',
        'email' => 'avairee@example.test',
        'customer_type' => 'Retail',
    ]);

    $body = hostedSmsFabricBody();
    [$raw, $server] = fabricSignedRequest($body);

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertOk();

    Event::assertDispatched(CommsInterruptReceived::class, function (CommsInterruptReceived $event) use ($customer): bool {
        $interrupt = $event->payload['interrupt'] ?? [];
        $replyUrl = (string) ($interrupt['reply_url'] ?? '');

        return (int) ($interrupt['customer_id'] ?? 0) === (int) $customer->id
            && str_contains($replyUrl, route('operations.customers.show', $customer))
            && str_contains($replyUrl, 'compose=text')
            && (int) ($interrupt['conversation_id'] ?? 0) > 0
            && filled($interrupt['mark_read_url'] ?? null)
            && ! str_contains((string) ($interrupt['mark_read_url'] ?? ''), (string) ($interrupt['platform_message_public_id'] ?? 'missing'));
    });
});

test('hosted dismissal uses the Core conversation id rather than a Platform public id', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    Event::fake([CommsInterruptReceived::class]);

    $body = hostedSmsFabricBody(['provider_message_id' => 'SMinbound-dismiss']);
    [$raw, $server] = fabricSignedRequest($body);
    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)->assertOk();

    $conversation = Conversation::query()->sole();
    $platformMessageId = '8f3c1d2a-1111-2222-3333-444444444444';

    $this->actingAs($advisor)
        ->postJson(route('operations.conversations.read', $conversation))
        ->assertOk();

    $this->actingAs($advisor)
        ->postJson('/app/api/conversations/'.$platformMessageId.'/read')
        ->assertNotFound();
});

test('hosted SMS interrupts stay isolated to the signed installation', function () {
    Event::fake([CommsInterruptReceived::class]);

    $body = hostedSmsFabricBody();
    [$raw, $server] = fabricSignedRequest($body, installationId: (string) Str::uuid());

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertUnauthorized();

    Event::assertNotDispatched(CommsInterruptReceived::class);
});

test('workstation privacy still suppresses hosted SMS popups in the browser contract', function () {
    $js = (string) file_get_contents(resource_path('js/ark-comms-interrupt.js'));

    expect($js)
        ->toContain('shouldSuppressInterrupt()')
        ->toContain('workstationPresenceGateActive()')
        ->toContain('ark-workstation-privacy-active')
        ->toContain('presentMessage(message, options = {})')
        ->toContain('if (this.shouldSuppressInterrupt())');
});

test('hosted SMS polling cannot recover a Platform-only message', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    Event::fake([CommsInterruptReceived::class]);

    $body = hostedSmsFabricBody(['provider_message_id' => 'SMinbound-poll']);
    [$raw, $server] = fabricSignedRequest($body);
    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)->assertOk();

    Event::assertDispatched(CommsInterruptReceived::class);

    $this->actingAs($advisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('messages', []);
});

test('missing hosted identities are rejected without leaking message contents', function () {
    Event::fake([CommsInterruptReceived::class]);
    Log::spy();

    $body = hostedSmsFabricBody([
        'message_public_id' => null,
        'provider_message_id' => 'SMinbound-no-identity',
    ]);
    [$raw, $server] = fabricSignedRequest($body);

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertOk()
        ->assertJsonPath('interrupt', false)
        ->assertJsonPath('reason', 'missing_message_identity');

    Event::assertNotDispatched(CommsInterruptReceived::class);

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
        $encoded = json_encode($context);

        return $message === 'hosted_sms.interrupt_rejected'
            && ($context['reason'] ?? null) === 'missing_message_identity'
            && ! str_contains((string) $encoded, 'Need an appointment')
            && ! str_contains((string) $encoded, '7195550199')
            && ! str_contains((string) $encoded, 'SMinbound-no-identity');
    })->atLeast()->once();
});

test('PHP interrupt payload satisfies the browser identity contract', function () {
    Event::fake([CommsInterruptReceived::class]);

    $body = hostedSmsFabricBody(['provider_message_id' => 'SMinbound-js-contract']);
    [$raw, $server] = fabricSignedRequest($body);
    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)->assertOk();

    $events = Event::dispatched(CommsInterruptReceived::class);
    expect($events)->not->toBeEmpty();

    $interrupt = $events[0][0]->payload['interrupt'] ?? [];
    $payloadPath = sys_get_temp_dir().'/hosted-sms-interrupt-'.Str::uuid().'.json';
    file_put_contents($payloadPath, json_encode($interrupt, JSON_THROW_ON_ERROR));

    $result = Process::path(base_path())->run([
        'node',
        'tests/js/hosted-sms-interrupt-contract.mjs',
        $payloadPath,
    ]);

    expect($result->successful())->toBeTrue($result->errorOutput().$result->output());
});

test('browser identity module covers hosted, mirrored, outbound, duplicate, and dismiss rules', function () {
    $result = Process::path(base_path())->run(['node', 'tests/js/hosted-sms-interrupt-contract.mjs']);

    expect($result->successful())->toBeTrue($result->errorOutput().$result->output());
});
