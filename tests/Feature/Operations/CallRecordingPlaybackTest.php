<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallRecordingPlayback;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\Media\CallSessionMediaLocator;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
});

test('a linked recording without a platform connection stays unavailable and Core does not call Twilio', function (): void {
    Http::fake();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $session = linkedRecordingSession();
    $locator = app(CallSessionMediaLocator::class);

    expect(config('services.twilio.account_sid'))->toBeNull()
        ->and(config('services.twilio.auth_token'))->toBeNull()
        ->and($locator->playbackAvailable())->toBeFalse()
        ->and($locator->canStream($session->recording_url))->toBeFalse()
        ->and($locator->fetch($session->recording_url))->toBeNull()
        ->and(app(CallRecordingPlayback::class)->projectFor($session)['recording_state'])->toBe('unavailable');

    $this->actingAs($advisor)
        ->get(route('operations.communications.calls'))
        ->assertOk()
        ->assertSee('Recording unavailable')
        ->assertDontSee('No audio', false)
        ->assertDontSee('<audio controls preload="none" class="ops-call-library__audio"', false)
        ->assertDontSee('api.twilio.com', false);

    Http::assertNothingSent();
});

test('a linked recording plays when Platform confirms ownership', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $session = linkedRecordingSession();
    $installationUuid = connectCoreToPlatform();
    $audio = 'ID3-platform-audio';

    Http::fake(function ($request) use ($installationUuid, $audio) {
        expect($request->url())->not->toContain('url=')
            ->and($request->url())->not->toContain('api.twilio.com')
            ->and($request->header('X-Ark-Installation-Id')[0] ?? null)->toBe($installationUuid)
            ->and($request->hasHeader('X-Ark-Signature'))->toBeTrue()
            ->and($request->hasHeader('X-Ark-Timestamp'))->toBeTrue()
            ->and($request->hasHeader('X-Ark-Nonce'))->toBeTrue()
            ->and(strtoupper($request->method()))->not->toBe('HEAD');

        if (str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/availability')) {
            expect(json_decode($request->body(), true))->toBe([recordingSid()]);

            return Http::response([
                'ok' => true,
                'available' => [recordingSid()],
            ], 200);
        }

        expect($request->url())->toBe('https://cloud.test/api/v1/services/voice/recordings/'.recordingSid())
            ->and($request->body())->toBe('[]')
            ->and(strtoupper($request->method()))->toBe('GET');

        return Http::response($audio, 200, ['Content-Type' => 'audio/mpeg']);
    });

    expect(config('services.twilio.account_sid'))->toBeNull()
        ->and(config('services.twilio.auth_token'))->toBeNull()
        ->and(app(CallRecordingPlayback::class)->projectFor($session)['recording_state'])->toBe('playable');

    $this->actingAs($advisor)
        ->get(route('operations.communications.calls'))
        ->assertOk()
        ->assertSee('<audio controls preload="none" class="ops-call-library__audio"', false)
        ->assertDontSee('Recording unavailable')
        ->assertDontSee('No audio', false)
        ->assertDontSee('api.twilio.com', false)
        ->assertDontSee('test-platform-recording-credential', false);

    $played = $this->actingAs($advisor)
        ->get(route('operations.telephony.call-sessions.recording', $session));

    $played->assertOk()
        ->assertHeader('content-type', 'audio/mpeg');

    expect($played->getContent())->toBe($audio)
        ->and($played->getContent())->not->toContain('test-platform-recording-credential')
        ->and($played->headers->has('Authorization'))->toBeFalse();

    $mobile = $this->actingAs($advisor, 'sanctum')
        ->get(route('api.mobile.telephony.call-sessions.recording', $session));

    $mobile->assertOk()
        ->assertHeader('content-type', 'audio/mpeg');

    expect($mobile->getContent())->toBe($audio)
        ->and($mobile->getContent())->not->toContain('test-platform-recording-credential')
        ->and($mobile->headers->has('Authorization'))->toBeFalse();
});

test('platform ownership missing leaves the recording unavailable', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $session = linkedRecordingSession();
    connectCoreToPlatform();

    Http::fake(function ($request) {
        expect($request->url())->not->toContain('api.twilio.com')
            ->and($request->url())->not->toContain('url=');

        return Http::response([
            'ok' => false,
            'reason_code' => 'recording_unavailable',
            'message' => 'Recording unavailable.',
        ], 404);
    });

    expect(app(CallRecordingPlayback::class)->projectFor($session)['recording_state'])->toBe('unavailable');

    $this->actingAs($advisor)
        ->get(route('operations.communications.calls'))
        ->assertOk()
        ->assertSee('Recording unavailable')
        ->assertDontSee('<audio controls preload="none" class="ops-call-library__audio"', false)
        ->assertDontSee('api.twilio.com', false);

    $this->withoutExceptionHandling();

    try {
        $this->actingAs($advisor)->get(route('operations.telephony.call-sessions.recording', $session));
        $messages = [];
    } catch (NotFoundHttpException $e) {
        $messages = exceptionMessages($e);
    }

    expect($messages)->toContain('Recording playback is not available.');
});

test('platform failure leaves the recording unavailable', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $session = linkedRecordingSession();
    connectCoreToPlatform();

    Http::fake(fn () => Http::response('platform-down', 503));

    expect(app(CallRecordingPlayback::class)->projectFor($session)['recording_state'])->toBe('unavailable');

    $this->actingAs($advisor)
        ->get(route('operations.communications.calls'))
        ->assertOk()
        ->assertSee('Recording unavailable')
        ->assertDontSee('platform-down', false)
        ->assertDontSee('<audio controls preload="none" class="ops-call-library__audio"', false);
});

test('a call with no recording does not pretend one exists', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio->value,
        'provider_call_sid' => 'CA-no-recording',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195550000',
        'to_number' => '+17194136227',
        'normalized_from' => '7195550000',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(5),
    ]);

    expect(app(CallRecordingPlayback::class)->projectFor($session))->toMatchArray([
        'has_recording' => false,
        'has_voicemail' => false,
        'recording_state' => 'none',
        'voicemail_state' => 'none',
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.calls'))
        ->assertOk()
        ->assertSee('No recording')
        ->assertDontSee('Recording unavailable')
        ->assertDontSee('<audio controls preload="none" class="ops-call-library__audio"', false);

    $this->withoutExceptionHandling();

    try {
        $this->actingAs($advisor)->get(route('operations.telephony.call-sessions.recording', $session));
        $messages = [];
    } catch (NotFoundHttpException $e) {
        $messages = [];
        $cursor = $e;
        while ($cursor instanceof Throwable) {
            $messages[] = $cursor->getMessage();
            $cursor = $cursor->getPrevious();
        }
    }

    expect($messages)->toContain('No recording.');
});

function linkedRecordingSession(): CallSession
{
    return CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio->value,
        'provider_call_sid' => 'CA-linked-recording',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551111',
        'to_number' => '+17194136227',
        'normalized_from' => '7195551111',
        'status' => CallSessionStatus::Completed,
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/ACtestaccount/Recordings/RE0123456789abcdef0123456789abcdef',
        'recording_sid' => 'RE0123456789abcdef0123456789abcdef',
        'started_at' => now()->subHour(),
    ]);
}

function recordingSid(): string
{
    return 'RE0123456789abcdef0123456789abcdef';
}

function connectCoreToPlatform(): string
{
    $installationUuid = (string) Str::uuid();
    InstallationIdentity::write($installationUuid);

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-recording-credential',
        'platform_base_url' => 'https://cloud.test',
        'platform_shop_public_id' => (string) Str::uuid(),
    ]);

    return $installationUuid;
}

/**
 * @return list<string>
 */
function exceptionMessages(Throwable $exception): array
{
    $messages = [];
    $cursor = $exception;

    while ($cursor instanceof Throwable) {
        $messages[] = $cursor->getMessage();
        $cursor = $cursor->getPrevious();
    }

    return $messages;
}
