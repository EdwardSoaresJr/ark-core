<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Operations\Telephony\TelephonySipOutboundDestination;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', null);
    config()->set('services.ark_platform.communications_authority', false);
    Http::fake();
    TelephonyEndpoint::query()->delete();
    ShopSettings::current()->persistTrusted(['telephony_inbound_number' => '+17195556227']);

    $this->owner = User::factory()->create();
    TelephonyEndpoint::query()->create([
        'name' => 'WP820 WiFi Phone',
        'type' => TelephonyEndpointType::Sip,
        'destination' => 'sip:wp820@lnp-chelton.sip.twilio.com',
        'user_id' => $this->owner->id,
        'enabled' => true,
        'position' => 0,
    ]);
});

function recordOutbound(bool $on): void
{
    ShopSettings::current()->persistTrusted(['telephony_call_flow' => array_merge(
        ShopSettings::defaultTelephonyCallFlow(),
        ['record_outbound_calls' => $on, 'recording_disclaimer' => 'This call may be recorded.'],
    )]);
}

function deskDial(string $dialed, string $callSid = 'CAdesk000001', string $from = 'sip:wp820@lnp-chelton.sip.twilio.com'): string
{
    return test()->post(route('webhooks.communications.twilio.voice.sip-outbound'), [
        'CallSid' => $callSid,
        'From' => $from,
        'To' => "sip:{$dialed}@lnp-chelton.sip.twilio.com",
        'Direction' => 'inbound',
        'CallStatus' => 'ringing',
    ])->assertOk()->getContent();
}

test('a registered desk phone dials out with the shop number and valid TwiML', function (): void {
    recordOutbound(false);

    $xml = deskDial('7195551234');

    preg_match('/<Dial\b([^>]*)>/', $xml, $dial);
    expect($dial[1] ?? '')->toContain('callerId="+17195556227"')
        ->and($dial[1])->toContain('answerOnBridge="true"')
        ->and($dial[1])->toContain('voice/sip-outbound/complete')
        ->and($dial[1])->not->toContain('statusCallback')
        ->and($dial[1])->not->toContain('record=')
        ->and($xml)->toMatch('/<Number statusCallback="[^"]+voice\/status"[^>]*>\+17195551234<\/Number>/');

    $session = CallSession::query()->sole();
    expect($session->provider_call_sid)->toBe('CAdesk000001')
        ->and($session->direction)->toBe(CallSessionDirection::Outbound)
        ->and($session->normalized_to)->toBe('7195551234')
        ->and($session->owned_by_user_id)->toBe($this->owner->id);
});

test('a retried webhook for the same call records one session', function (): void {
    deskDial('7195551234', 'CAdeskretry1');
    deskDial('7195551234', 'CAdeskretry1');

    expect(CallSession::query()->where('provider_call_sid', 'CAdeskretry1')->count())->toBe(1);
});

test('seven-digit, 1+10 and plus-one dialing all reach the same North American number', function (string $dialed): void {
    expect(deskDial($dialed))->toContain('>+17195551234</Number>');
})->with(['5551234', '17195551234', '%2B17195551234']);

test('a phone that is not a registered desk endpoint cannot place calls', function (): void {
    $xml = deskDial('7195551234', from: 'sip:stranger@lnp-chelton.sip.twilio.com');

    expect($xml)->not->toContain('<Dial')
        ->and($xml)->toContain('not set up to place calls')
        ->and(CallSession::query()->count())->toBe(0);
});

test('international, premium-rate and malformed numbers are refused', function (string $dialed): void {
    $xml = deskDial($dialed);

    expect($xml)->not->toContain('<Dial')->and($xml)->toContain('<Hangup/>')
        ->and(CallSession::query()->count())->toBe(0);
})->with(['011442071234567', '%2B442071234567', '9005551234', '411', 'abc']);

test('emergency calls always go through and are never recorded', function (): void {
    recordOutbound(true);

    $xml = deskDial('911');

    expect($xml)->toContain('>911</Number>')
        ->and($xml)->toContain('callerId="+17195556227"')
        ->and($xml)->not->toContain('record=');
});

test('recorded outbound calls play the notice to the person called and report the recording', function (): void {
    recordOutbound(true);

    $xml = deskDial('7195551234', 'CAdeskrec001');

    expect($xml)->toContain('record="record-from-answer-dual"')
        ->and($xml)->toContain('voice/recording')
        ->and($xml)->toContain('url="'.route('webhooks.communications.twilio.voice.sip-outbound-notice').'"');

    $notice = $this->post(route('webhooks.communications.twilio.voice.sip-outbound-notice'), ['CallSid' => 'CAchild'])->assertOk()->getContent();
    expect($notice)->toContain('<Say voice="alice">This call may be recorded.</Say>');

    $this->post(route('webhooks.communications.twilio.voice.recording'), [
        'CallSid' => 'CAdeskrec001',
        'RecordingSid' => 'RE00000000000000000000000000000001',
        'RecordingUrl' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Recordings/RE00000000000000000000000000000001',
        'RecordingDuration' => '42',
        'RecordingStatus' => 'completed',
    ])->assertSuccessful();

    expect(CallSession::query()->sole()->recording_sid)->toBe('RE00000000000000000000000000000001');
});

test('an unanswered outbound call tells the caller and never plays the shop voicemail', function (): void {
    deskDial('7195551234', 'CAdesknoans1');

    $xml = $this->post(route('webhooks.communications.twilio.voice.sip-outbound-complete'), [
        'CallSid' => 'CAdesknoans1',
        'DialCallStatus' => 'no-answer',
    ])->assertOk()->getContent();

    expect($xml)->toContain('There was no answer.')
        ->and($xml)->not->toContain('<Record')
        ->and(CallSession::query()->sole()->direction)->toBe(CallSessionDirection::Outbound);

    $answered = $this->post(route('webhooks.communications.twilio.voice.sip-outbound-complete'), [
        'CallSid' => 'CAdesknoans1',
        'DialCallStatus' => 'completed',
    ])->assertOk()->getContent();
    expect($answered)->toContain('<Hangup/>');
});

test('outbound webhooks require a valid Twilio signature', function (string $route): void {
    config()->set('services.twilio.auth_token', 'outbound-test-token');

    $this->post(route($route), ['CallSid' => 'CAforged', 'From' => 'sip:wp820@lnp-chelton.sip.twilio.com', 'To' => 'sip:7195551234@x'])
        ->assertStatus(401);
    expect(CallSession::query()->count())->toBe(0);
})->with([
    'webhooks.communications.twilio.voice.sip-outbound',
    'webhooks.communications.twilio.voice.sip-outbound-complete',
    'webhooks.communications.twilio.voice.sip-outbound-notice',
]);

test('the destination policy recognises each dialing form', function (): void {
    expect(TelephonySipOutboundDestination::resolve('sip:933@x', '+17195556227')['kind'])->toBe('emergency')
        ->and(TelephonySipOutboundDestination::resolve('sip:+18005550199@x', '+17195556227')['number'])->toBe('+18005550199')
        ->and(TelephonySipOutboundDestination::resolve('sip:1976555123@x', '+17195556227')['kind'])->toBe('rejected');
});

test('an emergency call goes out even from a phone ARK does not recognise', function (string $emergency): void {
    $xml = deskDial($emergency, 'CAemergency'.$emergency, from: 'sip:stranger@lnp-chelton.sip.twilio.com');

    expect($xml)->toContain('>'.$emergency.'</Number>')
        ->and($xml)->toContain('callerId="+17195556227"')
        ->and($xml)->not->toContain('record=');
})->with(['911', '933']);

test('an emergency call with no caller id tells the person to use another phone', function (): void {
    ShopSettings::current()->persistTrusted(['telephony_inbound_number' => null]);

    $xml = deskDial('911');

    expect($xml)->not->toContain('<Dial')
        ->and($xml)->toContain('Call nine one one from another phone');
});

test('NANP areas outside the US and Canada are refused unless enabled', function (string $dialed): void {
    $xml = deskDial($dialed);

    expect($xml)->not->toContain('<Dial')
        ->and($xml)->toContain('not enabled for this phone')
        ->and(CallSession::query()->count())->toBe(0);
})->with(['8765551234', '18095551234', '2845551234', '7875551234', '6715551234']);

test('Canadian and toll-free numbers are allowed and non-geographic codes are refused', function (): void {
    expect(deskDial('4165551234', 'CAcanada'))->toContain('>+14165551234</Number>')
        ->and(deskDial('8005551234', 'CAtollfree'))->toContain('>+18005551234</Number>')
        ->and(deskDial('5005551234', 'CAnongeo'))->not->toContain('<Dial')
        ->and(deskDial('7005551234', 'CAnongeo2'))->not->toContain('<Dial');
});

test('an area outside the US and Canada can be enabled explicitly', function (): void {
    config()->set('telephony.sip_outbound.allowed_area_codes', ['787']);

    expect(deskDial('7875551234'))->toContain('>+17875551234</Number>')
        ->and(deskDial('8765551234', 'CAstillblocked'))->not->toContain('<Dial');
});

test('a recorded call with no notice text configured still announces the default notice first', function (): void {
    ShopSettings::current()->persistTrusted(['telephony_call_flow' => array_merge(
        ShopSettings::defaultTelephonyCallFlow(),
        ['record_outbound_calls' => true, 'recording_disclaimer' => '   '],
    )]);

    $xml = deskDial('7195551234', 'CAdefaultnotice');
    $notice = $this->post(route('webhooks.communications.twilio.voice.sip-outbound-notice'), ['CallSid' => 'CAchild'])->assertOk()->getContent();

    expect($xml)->toContain('record="record-from-answer-dual"')
        ->and($xml)->toContain('url="'.route('webhooks.communications.twilio.voice.sip-outbound-notice').'"')
        ->and($notice)->toContain('This call may be recorded for quality and training purposes.');
});
