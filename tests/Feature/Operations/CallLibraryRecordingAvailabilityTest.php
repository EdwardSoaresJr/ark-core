<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
});

test('a calls page asks platform once for thirty recording sids', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    connectAvailabilityPlatform();
    $owned = [];

    for ($i = 1; $i <= 50; $i++) {
        $sid = availabilitySid($i);
        if ($i <= 30) {
            $owned[] = $sid;
        }
        availabilityCall($i, $i <= 30 ? $sid : null);
    }

    $requests = [];
    Http::fake(function ($request) use (&$requests, $owned) {
        $requests[] = availabilityRequest($request);
        expect(strtoupper($request->method()))->not->toBe('HEAD')
            ->and($request->url())->not->toContain('api.twilio.com');

        if (strtoupper($request->method()) === 'GET') {
            return Http::response('ID3-audio', 200, ['Content-Type' => 'audio/mpeg']);
        }

        return Http::response([
            'ok' => true,
            'available' => array_slice($owned, 0, 10),
        ]);
    });

    $this->actingAs($advisor)
        ->get(route('operations.communications.calls'))
        ->assertOk()
        ->assertSee('<audio controls preload="none" class="ops-call-library__audio"', false)
        ->assertSee('Recording unavailable')
        ->assertSee('No recording')
        ->assertDontSee('api.twilio.com', false)
        ->assertDontSee('test-platform-recording-credential', false);

    $availability = array_values(array_filter(
        $requests,
        fn (array $request): bool => str_ends_with($request['path'], '/availability'),
    ));

    expect($availability)->toHaveCount(1)
        ->and($availability[0]['method'])->toBe('POST')
        ->and($availability[0]['sids'])->toHaveCount(30)
        ->and($requests)->toHaveCount(1);

    $played = CallSession::query()->where('recording_sid', availabilitySid(1))->firstOrFail();
    $this->actingAs($advisor)
        ->get(route('operations.telephony.call-sessions.recording', $played))
        ->assertOk()
        ->assertHeader('content-type', 'audio/mpeg');

    $playback = array_values(array_filter(
        $requests,
        fn (array $request): bool => $request['method'] === 'GET'
            && str_contains($request['path'], '/recordings/'.availabilitySid(1)),
    ));
    $availabilityAfterPlay = array_values(array_filter(
        $requests,
        fn (array $request): bool => str_ends_with($request['path'], '/availability'),
    ));

    expect($playback)->toHaveCount(1)
        ->and($availabilityAfterPlay)->toHaveCount(1)
        ->and(config('services.twilio.account_sid'))->toBeNull()
        ->and(config('services.twilio.auth_token'))->toBeNull();
});

test('recording and voicemail sids share one deduplicated availability request', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    connectAvailabilityPlatform();

    for ($i = 1; $i <= 20; $i++) {
        availabilityCall($i, availabilitySid($i));
    }
    for ($i = 21; $i <= 30; $i++) {
        availabilityCall($i, null, availabilitySid($i));
    }
    for ($i = 31; $i <= 35; $i++) {
        availabilityCall($i, availabilitySid(1));
    }
    for ($i = 36; $i <= 50; $i++) {
        availabilityCall($i, null);
    }

    $requests = [];
    Http::fake(function ($request) use (&$requests) {
        $requests[] = availabilityRequest($request);

        return Http::response(['ok' => true, 'available' => []], 200);
    });

    $this->actingAs($advisor)
        ->get(route('operations.communications.calls'))
        ->assertOk()
        ->assertSee('Recording unavailable')
        ->assertSee('Voicemail unavailable')
        ->assertSee('No recording');

    $availability = array_values(array_filter(
        $requests,
        fn (array $request): bool => str_ends_with($request['path'], '/availability'),
    ));

    expect($requests)->toHaveCount(1)
        ->and($availability)->toHaveCount(1)
        ->and($availability[0]['sids'])->toHaveCount(30)
        ->and($availability[0]['sids'])->toContain(availabilitySid(1))
        ->and($availability[0]['sids'])->toContain(availabilitySid(30));
});

test('a platform outage still renders the calls page', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    connectAvailabilityPlatform();
    availabilityCall(1, availabilitySid(1));

    Http::fake(fn () => Http::response('platform-down', 503));

    $this->actingAs($advisor)
        ->get(route('operations.communications.calls'))
        ->assertOk()
        ->assertSee('Recording unavailable')
        ->assertDontSee('platform-down', false)
        ->assertDontSee('<audio controls preload="none" class="ops-call-library__audio"', false);

    Http::fake(function (): void {
        throw new ConnectionException('timed out');
    });

    $this->actingAs($advisor)
        ->get(route('operations.communications.calls'))
        ->assertOk()
        ->assertSee('Recording unavailable');
});

function availabilitySid(int $n): string
{
    return 'RE'.sprintf('%032x', $n);
}

function availabilityCall(int $n, ?string $recordingSid, ?string $voicemailSid = null): CallSession
{
    return CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio->value,
        'provider_call_sid' => 'CA-availability-'.$n,
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+1719555'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
        'to_number' => '+17194136227',
        'normalized_from' => '719555'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
        'status' => CallSessionStatus::Completed,
        'recording_url' => $recordingSid === null
            ? null
            : 'https://api.twilio.com/2010-04-01/Accounts/ACtestaccount/Recordings/'.$recordingSid,
        'recording_sid' => $recordingSid,
        'voicemail_url' => $voicemailSid === null
            ? null
            : 'https://api.twilio.com/2010-04-01/Accounts/ACtestaccount/Recordings/'.$voicemailSid,
        'voicemail_sid' => $voicemailSid,
        'started_at' => now()->subMinutes($n),
    ]);
}

function connectAvailabilityPlatform(): void
{
    InstallationIdentity::write((string) Str::uuid());
    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-recording-credential',
        'platform_base_url' => 'https://cloud.test',
        'platform_shop_public_id' => (string) Str::uuid(),
    ]);
}

/**
 * @return array{method: string, path: string, sids: list<string>}
 */
function availabilityRequest(object $request): array
{
    $path = (string) parse_url($request->url(), PHP_URL_PATH);
    $sids = [];
    if (str_ends_with($path, '/availability')) {
        $decoded = json_decode($request->body(), true);
        $sids = is_array($decoded) ? array_values($decoded) : [];
    }

    return [
        'method' => strtoupper($request->method()),
        'path' => $path,
        'sids' => $sids,
    ];
}
