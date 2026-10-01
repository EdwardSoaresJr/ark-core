<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationMessageAttachment;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Platform\Communications\PlatformSmsTimelineProjection;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    config()->set('broadcasting.default', 'null');
    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_inbox', true);
    config()->set('services.ark_platform.communications_send', true);
    config()->set('services.ark_platform.communications_core_mirror', false);
    config()->set('app.display_timezone', 'America/Denver');

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-credential',
        'platform_base_url' => 'https://cloud.test',
    ]);
});

test('the inbox renders stored photos and does not expose provider urls', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $work = app(ConversationWork::class);
    $work->markNeedsAttention($work->ensureForPhone('7195550184'));
    mmsCustomer('Photo Customer', '7195550184');

    $photoId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    $fileId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
    mmsFakeThreads([
        mmsThread('pc_photo', '+17195550184', [
            [
                'public_id' => '11111111-1111-1111-1111-111111111111',
                'direction' => 'inbound',
                'body' => '',
                'occurred_at' => now()->subMinute()->toIso8601String(),
                'delivery_status' => 'received',
                'attachments' => [[
                    'public_id' => $photoId,
                    'disposition' => 'stored',
                    'content_type' => 'image/jpeg',
                    'byte_size' => 120,
                    'provider_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Messages/MM/Media/MEsecret',
                ]],
            ],
            [
                'public_id' => '22222222-2222-2222-2222-222222222222',
                'direction' => 'inbound',
                'body' => '',
                'occurred_at' => now()->toIso8601String(),
                'delivery_status' => 'received',
                'attachments' => [[
                    'public_id' => $fileId,
                    'disposition' => 'unsupported',
                    'content_type' => 'video/3gpp',
                    'provider_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Messages/MM/Media/MEvideo',
                ]],
            ],
        ], preview: 'Unsupported attachment'),
    ]);

    $page = $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'needs',
            'platform_conversation' => 'pc_photo',
        ]))
        ->assertOk();

    $html = $page->getContent();
    $thread = substr($html, (int) strpos($html, 'id="comms-workspace-thread-messages"'));

    expect($html)->toContain('Unsupported attachment')
        ->and($thread)->toContain('alt="Photo"')
        ->and($thread)->toContain('/app/communications/attachments/'.$photoId)
        ->and($thread)->toContain('Unsupported attachment')
        ->and($thread)->not->toContain('api.twilio.com')
        ->and($thread)->not->toContain('video/3gpp')
        ->and($thread)->not->toContain('MEsecret');
});

test('a media-only inbox row previews photo and a failed send stays on the message', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $work = app(ConversationWork::class);
    $work->markNeedsAttention($work->ensureForPhone('7195550185'));
    mmsCustomer('Failed Photo', '7195550185');

    mmsFakeThreads([
        mmsThread('pc_failed', '+17195550185', [
            [
                'public_id' => '33333333-3333-3333-3333-333333333333',
                'direction' => 'outbound',
                'body' => '',
                'occurred_at' => now()->toIso8601String(),
                'delivery_status' => 'failed',
                'attachments' => [[
                    'public_id' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
                    'disposition' => 'stored',
                    'content_type' => 'image/jpeg',
                ]],
            ],
        ], preview: 'Photo', deliveryStatus: 'failed'),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'needs']))
        ->assertOk()
        ->assertSee('Not delivered · Photo')
        ->assertDontSee('api.twilio.com');
});

test('the customer projection renders canonical attachments without provider urls', function () {
    $customer = mmsCustomer('Timeline Photo', '7195550186');
    $photoId = 'dddddddd-dddd-dddd-dddd-dddddddddddd';

    Http::fake(function ($request) use ($photoId) {
        $url = $request->url();
        if (str_contains($url, '/conversations/pc_timeline')) {
            return Http::response([
                'ok' => true,
                'conversation' => [
                    'public_id' => 'pc_timeline',
                    'contact_address' => '+17195550186',
                    'core_customer_id' => null,
                ],
                'messages' => [[
                    'public_id' => '44444444-4444-4444-4444-444444444444',
                    'direction' => 'inbound',
                    'body' => 'See this',
                    'occurred_at' => now()->toIso8601String(),
                    'delivery_status' => 'received',
                    'attachments' => [
                        [
                            'public_id' => $photoId,
                            'disposition' => 'stored',
                            'content_type' => 'image/jpeg',
                        ],
                        [
                            'public_id' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
                            'disposition' => 'unavailable',
                            'provider_url' => 'https://api.twilio.com/old-media',
                        ],
                    ],
                ]],
            ]);
        }

        return Http::response([
            'ok' => true,
            'conversations' => [[
                'public_id' => 'pc_timeline',
                'contact_address' => '+17195550186',
                'last_message_at' => now()->toIso8601String(),
                'preview' => 'See this',
                'delivery_status' => 'received',
            ]],
        ]);
    });

    $entry = app(PlatformSmsTimelineProjection::class)
        ->forCustomer($customer, '7195550186')
        ->first();

    expect($entry)->not->toBeNull();

    $html = view('operations.timeline.partials.event-bubble', ['event' => $entry])->render();

    expect($html)->toContain('See this')
        ->and($html)->toContain('alt="Photo"')
        ->and($html)->toContain('/app/communications/attachments/'.$photoId)
        ->and($html)->toContain('Attachment unavailable')
        ->and($html)->toContain('MMS')
        ->and($html)->not->toContain('api.twilio.com')
        ->and($html)->not->toContain('old-media');
});

test('the inbox composer sends files through the platform conversation endpoint', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    Storage::fake('local');

    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, '/conversations/pc_compose') && $request->method() === 'GET') {
            return Http::response([
                'ok' => true,
                'conversation' => [
                    'public_id' => 'pc_compose',
                    'contact_address' => '+17195550187',
                ],
                'messages' => [],
            ]);
        }

        if (str_contains($url, '/messages/conversation')) {
            return Http::response([
                'ok' => true,
                'status' => 'provider_sent',
                'provider_message_id' => 'MMcompose',
                'comm_message_public_id' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
                'attachments' => [
                    [
                        'public_id' => '12121212-1212-1212-1212-121212121212',
                        'disposition' => 'stored',
                        'content_type' => 'image/jpeg',
                    ],
                    [
                        'public_id' => '13131313-1313-1313-1313-131313131313',
                        'disposition' => 'stored',
                        'content_type' => 'image/png',
                    ],
                ],
            ]);
        }

        return Http::response(['ok' => false], 503);
    });

    $response = $this->actingAs($advisor)->post(
        route('operations.communications.platform-conversations.messages', ['platformConversation' => 'pc_compose']),
        [
            'attachments' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.png'),
            ],
        ],
    );

    $response->assertOk()
        ->assertJsonPath('platform_message.body', '')
        ->assertJsonPath('platform_message.attachments.0.kind', 'image')
        ->assertJsonPath('platform_message.attachments.0.label', 'Photo')
        ->assertJsonPath('platform_message.attachments.1.kind', 'image');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/messages/conversation')) {
            return false;
        }

        $data = $request->data();

        return ($data['operation'] ?? null) === 'conversation.send'
            && ($data['body'] ?? null) === ''
            && count($data['media_urls'] ?? []) === 2
            && ! str_contains($request->url(), 'twilio');
    });

    expect(ConversationMessage::query()->count())->toBe(0);
});

test('hosted inbound mms does not mirror a core message or attachment', function () {
    InstallationIdentity::write((string) Str::uuid());
    ShopSettings::current()->persistTrusted([
        'shop_name' => 'Casey Auto Repair',
        'platform_status' => 'connected',
        'platform_base_url' => 'https://cloud.test',
        'platform_credential' => 'test-credential-32-characters-min!!',
        'platform_shop_public_id' => (string) Str::uuid(),
    ]);

    $body = [
        'operation' => 'sms.incoming.received',
        'installation_id' => InstallationIdentity::uuid(),
        'occurred_at' => now()->toIso8601String(),
        'payload' => [
            'from_phone' => '+17195550190',
            'to_phone' => '+17195550100',
            'body' => '',
            'provider_message_id' => 'MMinbound-hosted-1',
            'message_public_id' => (string) Str::uuid(),
            'conversation_public_id' => (string) Str::uuid(),
            'media' => [[
                'url' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Messages/MM/Media/MEhosted',
                'content_type' => 'image/jpeg',
            ]],
        ],
    ];

    [$raw, $server] = fabricSignedRequest($body);

    $this->call('POST', '/webhooks/cloud/fabric/events', [], [], [], $server, $raw)
        ->assertOk()
        ->assertJsonPath('mirrored', false);

    expect(ConversationMessage::query()->count())->toBe(0)
        ->and(ConversationMessageAttachment::query()->count())->toBe(0);
});

/**
 * @param  list<array<string, mixed>>  $threads
 */
function mmsFakeThreads(array $threads): void
{
    $byId = [];
    foreach ($threads as $thread) {
        $byId[$thread['public_id']] = $thread;
    }

    Http::fake(function ($request) use ($byId) {
        $url = $request->url();

        if (str_contains($url, '/read')) {
            return Http::response(['ok' => true], 200);
        }

        foreach ($byId as $publicId => $thread) {
            if (str_contains($url, '/conversations/'.$publicId)) {
                return Http::response([
                    'ok' => true,
                    'conversation' => [
                        'public_id' => $publicId,
                        'contact_address' => $thread['contact_address'],
                    ],
                    'messages' => $thread['messages'],
                ], 200);
            }
        }

        return Http::response([
            'ok' => true,
            'conversations' => array_map(fn (array $thread): array => [
                'public_id' => $thread['public_id'],
                'contact_address' => $thread['contact_address'],
                'last_message_at' => $thread['last_message_at'],
                'preview' => $thread['preview'],
                'delivery_status' => $thread['delivery_status'],
                'unread' => false,
            ], array_values($byId)),
        ], 200);
    });
}

/**
 * @param  list<array<string, mixed>>  $messages
 * @return array<string, mixed>
 */
function mmsThread(string $publicId, string $phone, array $messages, string $preview = '', string $deliveryStatus = 'received'): array
{
    return [
        'public_id' => $publicId,
        'contact_address' => $phone,
        'messages' => $messages,
        'preview' => $preview,
        'delivery_status' => $deliveryStatus,
        'last_message_at' => now()->toIso8601String(),
    ];
}

function mmsCustomer(string $name, string $phone): Customer
{
    [$first, $last] = array_pad(explode(' ', $name, 2), 2, 'Customer');

    return Customer::query()->create([
        'first_name' => $first,
        'last_name' => $last,
        'phone' => $phone,
        'email' => $phone.'@example.test',
    ]);
}
