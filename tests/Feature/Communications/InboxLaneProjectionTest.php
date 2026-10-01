<?php

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\HostedCallOutcome;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    config()->set('broadcasting.default', 'null');
    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_inbox', true);
    config()->set('services.ark_platform.communications_core_mirror', false);

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-credential',
        'platform_base_url' => 'https://cloud.test',
    ]);
});

test('the needs lane previews the latest text or call instead of the lane name', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $work = app(ConversationWork::class);
    $work->markNeedsAttention($work->ensureForPhone('7195550301'));

    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-lane-missed',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195550302',
        'to_number' => '+17195550100',
        'normalized_from' => '7195550302',
        'status' => CallSessionStatus::Missed,
        'hosted_outcome' => HostedCallOutcome::Missed,
        'started_at' => now()->subMinutes(5),
        'ended_at' => now()->subMinutes(5),
    ]);

    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-lane-voicemail',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195550304',
        'to_number' => '+17195550100',
        'normalized_from' => '7195550304',
        'status' => CallSessionStatus::Missed,
        'hosted_outcome' => HostedCallOutcome::VoicemailLeft,
        'voicemail_url' => 'https://recordings.example.test/lane-vm',
        'started_at' => now()->subMinutes(4),
        'ended_at' => now()->subMinutes(4),
    ]);

    Http::fake([
        'https://cloud.test/api/v1/services/communications/conversations*' => function ($request) {
            if (str_contains($request->url(), '/read') || preg_match('#/conversations/pc_lane$#', $request->url())) {
                return Http::response([
                    'ok' => true,
                    'conversation' => [
                        'public_id' => 'pc_lane',
                        'contact_address' => '+17195550301',
                    ],
                    'messages' => [[
                        'public_id' => 'pm_lane',
                        'direction' => 'outbound',
                        'body' => 'Your car is ready',
                        'occurred_at' => now()->toIso8601String(),
                        'delivery_status' => 'failed',
                    ]],
                ], 200);
            }

            return Http::response([
                'ok' => true,
                'conversations' => [[
                    'public_id' => 'pc_lane',
                    'contact_address' => '+17195550301',
                    'last_message_at' => now()->toIso8601String(),
                    'preview' => 'Your car is ready',
                    'delivery_status' => 'failed',
                    'unread' => false,
                ]],
            ], 200);
        },
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'needs']))
        ->assertOk()
        ->assertSee('Not delivered · Your car is ready')
        ->assertSee('Missed call')
        ->assertSee('Voicemail')
        ->assertSee('Phone')
        ->assertSee('SMS');
});

test('a resolved thread reopened by hosted sms previews the message body', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $work = app(ConversationWork::class);
    $work->resolve($work->ensureForPhone('7195550305'), $advisor);

    app(\App\Ark\Operations\Messaging\InboundSmsConversationIngress::class)->ingest(new \App\Ark\Operations\Conversations\InboundConversationPayload(
        contactSurface: \App\Ark\Operations\Conversations\ConversationContactSurface::Phone,
        contactKey: '7195550305',
        providerMessageId: 'SM-lane-reopen',
        channel: \App\Ark\Operations\Communications\OperationalCommunicationChannel::Sms,
        body: 'Can you let me know if the car is ready?',
    ));

    Http::fake([
        'https://cloud.test/api/v1/services/communications/conversations*' => Http::response([
            'ok' => true,
            'conversations' => [[
                'public_id' => 'pc_reopen',
                'contact_address' => '+17195550305',
                'last_message_at' => now()->toIso8601String(),
                'preview' => 'Can you let me know if the car is ready?',
                'delivery_status' => 'received',
                'unread' => true,
            ]],
            'conversation' => [
                'public_id' => 'pc_reopen',
                'contact_address' => '+17195550305',
            ],
            'messages' => [],
        ], 200),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'needs']))
        ->assertOk()
        ->assertSee('Can you let me know if the car is ready?')
        ->assertSee('Needs');

    expect(ConversationMessage::query()->where('body', 'Can you let me know if the car is ready?')->exists())->toBeFalse();
});

test('voicemail offered with nothing left previews as a missed call in Needs', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-lane-offered',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195550306',
        'to_number' => '+17195550100',
        'normalized_from' => '7195550306',
        'status' => CallSessionStatus::Missed,
        'hosted_outcome' => HostedCallOutcome::VoicemailOffered,
        'started_at' => now()->subMinutes(3),
        'ended_at' => now()->subMinutes(3),
    ]);

    Http::fake([
        'https://cloud.test/api/v1/services/communications/conversations*' => Http::response([
            'ok' => true,
            'conversations' => [],
        ], 200),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'needs']))
        ->assertOk()
        ->assertSee('Missed call')
        ->assertDontSee('Voicemail');
});

test('an answered call stays out of Needs and still reads as answered', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $work = app(ConversationWork::class);
    $work->resolve($work->ensureForPhone('7195550303'), $advisor);

    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-lane-answered',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195550303',
        'to_number' => '+17195550100',
        'normalized_from' => '7195550303',
        'status' => CallSessionStatus::Completed,
        'hosted_outcome' => HostedCallOutcome::Completed,
        'answered_at' => now()->subMinutes(10),
        'started_at' => now()->subMinutes(12),
        'ended_at' => now()->subMinutes(10),
    ]);

    Http::fake([
        'https://cloud.test/api/v1/services/communications/conversations*' => Http::response([
            'ok' => true,
            'conversations' => [],
        ], 200),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'needs']))
        ->assertOk()
        ->assertDontSee('Answered call');

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'resolved']))
        ->assertOk()
        ->assertSee('Answered call');
});
