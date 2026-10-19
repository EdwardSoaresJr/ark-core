<?php

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Platform\Communications\ManagedCommunicationsGate;
use App\Ark\Platform\PlatformConnection;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.account_sid', null);
    config()->set('services.twilio.auth_token', null);
    config()->set('services.twilio.from', null);
    config()->set('services.ark_platform.communications_authority', true);
    TelephonyEndpoint::query()->delete();
});

test('open hours voice webhook returns one dial for every ring destination', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'America/Denver'));
    ring('Front', '+17195550111');
    ring('Advisor', '+17195550222');

    $response = $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAnativeopen01',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'CallStatus' => 'ringing',
    ])->assertOk();

    $xml = $response->getContent();
    preg_match('/<Dial\b([^>]*)>/', $xml, $dial);
    expect($xml)->toContain('<Dial')
        ->and($xml)->toContain('+17195550111')
        ->and($xml)->toContain('+17195550222')
        ->and($xml)->not->toContain('url=""')
        ->and($dial[1] ?? '')->toContain('answerOnBridge="true"')
        ->and($dial[1] ?? '')->not->toContain('statusCallback')
        ->and(substr_count($xml, '<Number'))->toBe(2)
        ->and(CallSession::query()->where('provider_call_sid', 'CAnativeopen01')->count())->toBe(1);

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAnativeopen01',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'CallStatus' => 'ringing',
    ])->assertOk();

    expect(CallSession::query()->where('provider_call_sid', 'CAnativeopen01')->count())->toBe(1);
    Carbon::setTestNow();
});

test('after hours and an unanswered dial go to voicemail', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-18 10:00:00', 'America/Denver'));
    ring('Front', '+17195550111');

    $closed = $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAnativeclosed1',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'CallStatus' => 'ringing',
    ])->assertOk()->getContent();

    expect($closed)->toContain('currently closed')
        ->and($closed)->toContain('<Record');

    Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'America/Denver'));
    $unanswered = $this->post(route('webhooks.communications.twilio.voice.dial-complete'), [
        'CallSid' => 'CAnativeclosed1',
        'DialCallStatus' => 'no-answer',
    ])->assertOk()->getContent();

    expect($unanswered)->toContain('<Record')
        ->and($unanswered)->toContain('voice/recording');
    Carbon::setTestNow();
});

test('an answered call is marked answered once', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'America/Denver'));
    ring('Front', '+17195550111');

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAnativeanswer',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'CallStatus' => 'ringing',
    ])->assertOk();

    $body = [
        'CallSid' => 'CAnativeanswer',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'CallStatus' => 'in-progress',
    ];
    $this->post(route('webhooks.communications.twilio.voice.status'), $body)->assertNoContent();
    $this->post(route('webhooks.communications.twilio.voice.status'), $body)->assertNoContent();

    $session = CallSession::query()->where('provider_call_sid', 'CAnativeanswer')->first();
    expect($session)->not->toBeNull()
        ->and($session->status)->toBe(CallSessionStatus::Answered)
        ->and($session->answered_at)->not->toBeNull()
        ->and(CallSession::query()->where('provider_call_sid', 'CAnativeanswer')->count())->toBe(1);
    Carbon::setTestNow();
});

test('a missed call is recorded once and companion can read the session', function (): void {
    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAnativemissed',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'CallStatus' => 'ringing',
    ])->assertOk();

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => 'CAnativemissed',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'CallStatus' => 'no-answer',
    ])->assertNoContent();
    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => 'CAnativemissed',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'CallStatus' => 'no-answer',
    ])->assertNoContent();

    $session = CallSession::query()->where('provider_call_sid', 'CAnativemissed')->first();
    expect($session?->status)->toBe(CallSessionStatus::Missed);

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($admin, 'sanctum')
        ->getJson(route('api.mobile.incoming-call.context', ['call_session_id' => $session->id]))
        ->assertOk()
        ->assertJsonPath('call_session_id', $session->id);
});

test('closed hours send the caller to voicemail and keep the session missed', function (): void {
    $flow = \App\Ark\Operations\Settings\ShopSettings::current()->telephony_call_flow;
    $flow['weekly_hours'] = [
        'monday' => ['enabled' => false, 'open' => '00:00', 'close' => '00:00'],
        'tuesday' => ['enabled' => false, 'open' => '00:00', 'close' => '00:00'],
        'wednesday' => ['enabled' => false, 'open' => '00:00', 'close' => '00:00'],
        'thursday' => ['enabled' => false, 'open' => '00:00', 'close' => '00:00'],
        'friday' => ['enabled' => false, 'open' => '00:00', 'close' => '00:00'],
        'saturday' => ['enabled' => false, 'open' => '00:00', 'close' => '00:00'],
        'sunday' => ['enabled' => false, 'open' => '00:00', 'close' => '00:00'],
    ];
    \App\Ark\Operations\Settings\ShopSettings::current()->update(['telephony_call_flow' => $flow]);

    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAnativeclosed',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'CallStatus' => 'ringing',
    ])->assertOk()
        ->assertSee('voicemail=1', false)
        ->assertSee('<Record', false)
        ->assertDontSee('<Dial', false);

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => 'CAnativeclosed',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'CallStatus' => 'in-progress',
    ])->assertNoContent();

    $session = CallSession::query()->where('provider_call_sid', 'CAnativeclosed')->first();
    expect($session?->status)->toBe(CallSessionStatus::Missed)
        ->and($session?->answered_at)->toBeNull();
});

test('an unanswered dial stays missed after the parent call completes', function (): void {
    $this->post(route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAnativedialmiss',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'CallStatus' => 'ringing',
    ])->assertOk();

    $this->post(route('webhooks.communications.twilio.voice.dial-complete'), [
        'CallSid' => 'CAnativedialmiss',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'DialCallStatus' => 'no-answer',
        'CallStatus' => 'in-progress',
    ])->assertOk()
        ->assertSee('<Record', false);

    $this->post(route('webhooks.communications.twilio.voice.status'), [
        'CallSid' => 'CAnativedialmiss',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'CallStatus' => 'completed',
        'CallDuration' => '12',
    ])->assertNoContent();

    $session = CallSession::query()->where('provider_call_sid', 'CAnativedialmiss')->first();
    expect($session?->status)->toBe(CallSessionStatus::Missed)
        ->and($session?->answered_at)->toBeNull()
        ->and($session?->ended_at)->not->toBeNull();
});

test('inbound sms is stored once and a bad signature is rejected', function (): void {
    config()->set('broadcasting.default', 'null');

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMnativeonce01',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'Body' => 'The truck is ready.',
    ])->assertOk();
    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMnativeonce01',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'Body' => 'The truck is ready.',
    ])->assertOk();

    expect(ConversationMessage::query()->where('body', 'The truck is ready.')->count())->toBe(1);

    config()->set('services.twilio.auth_token', 'test-token');
    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMnativebad01',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'Body' => 'This must not be stored.',
    ], ['X-Twilio-Signature' => 'not-a-signature'])->assertStatus(401);

    expect(ConversationMessage::query()->where('body', 'This must not be stored.')->count())->toBe(0);
    expect(app(ManagedCommunicationsGate::class)->authority())->toBeFalse()
        ->and(PlatformConnection::current()->isConnected())->toBeFalse();
});

test('outbound sms uses the shop twilio account and reports a provider failure', function (): void {
    config()->set('services.twilio.account_sid', 'ACnative');
    config()->set('services.twilio.auth_token', 'native-token');
    config()->set('services.twilio.from', '+17195550100');

    Http::fake([
        'api.twilio.com/*' => Http::sequence()
            ->push(['sid' => 'SMoutbound01', 'status' => 'queued'], 201)
            ->push(['code' => 21610], 400),
    ]);

    $transport = app(\App\Ark\Operations\Messaging\OutboundSmsTransport::class);
    $result = $transport->send('+17195551234', 'Your estimate is ready.');

    expect($result->messageId)->toBe('SMoutbound01')
        ->and($result->status)->toBe('queued');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/ACnative/Messages.json'
        && $request['From'] === '+17195550100'
        && ! str_contains($request->url(), 'cloud.arksms.com'));

    expect(fn () => $transport->send('+17195551234', 'Again'))
        ->toThrow(RuntimeException::class);
});

function ring(string $name, string $destination): TelephonyEndpoint
{
    return TelephonyEndpoint::query()->create([
        'name' => $name,
        'type' => TelephonyEndpointType::Cell,
        'destination' => $destination,
        'enabled' => true,
        'position' => 0,
        'ring_delay_seconds' => 0,
    ]);
}
