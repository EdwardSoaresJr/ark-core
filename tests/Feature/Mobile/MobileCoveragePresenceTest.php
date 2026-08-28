<?php

use App\Ark\Mobile\MobileDevice;
use App\Ark\Operations\Communications\CommunicationsShopProjection;
use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceEndpointRegistrar;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TelephonyEndpointType;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('mobile staff show available in coverage only while voice-ready recently', function (): void {
    $landon = User::factory()->create(['name' => 'Landon Carter'])->assignRole(ArkRole::Technician->value);
    $token = $landon->createToken('Landon iPhone')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/mobile/device', [
            'device_name' => 'Landon iPhone',
            'platform' => 'android',
        ])
        ->assertOk();

    $coverage = CommunicationsShopProjection::forCurrentShop()->resolve()['coverage'];
    $landonRow = collect($coverage)->first(fn ($row) => $row->name === 'Landon Carter');

    expect($landonRow)->not->toBeNull()
        ->and($landonRow->summary)->toBe('Offline');

    $device = MobileDevice::query()->where('user_id', $landon->id)->firstOrFail();
    app(MobileVoiceEndpointRegistrar::class)->markVoiceReady($device);

    $coverage = CommunicationsShopProjection::forCurrentShop()->resolve()['coverage'];
    $landonRow = collect($coverage)->first(fn ($row) => $row->name === 'Landon Carter');

    expect($landonRow)->not->toBeNull()
        ->and($landonRow->summary)->toBe('Available');

    $this->withToken($token)
        ->postJson('/api/mobile/auth/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Signed out.');

    $coverage = CommunicationsShopProjection::forCurrentShop()->resolve()['coverage'];
    $landonRow = collect($coverage)->first(fn ($row) => $row->name === 'Landon Carter');

    expect($landonRow)->not->toBeNull()
        ->and($landonRow->summary)->toBe('Offline');

    expect(TelephonyEndpoint::query()
        ->where('user_id', $landon->id)
        ->where('type', TelephonyEndpointType::MobileApp)
        ->where('enabled', true)
        ->exists())->toBeFalse();
});

test('stale mobile voice_ready does not count as coverage', function (): void {
    Carbon::setTestNow('2026-06-28 12:00:00');

    $landon = User::factory()->create(['name' => 'Landon Carter'])->assignRole(ArkRole::Technician->value);
    $token = $landon->createToken('Landon iPhone')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/mobile/device', [
            'device_name' => 'Landon iPhone',
            'platform' => 'android',
        ])
        ->assertOk();

    $device = MobileDevice::query()->where('user_id', $landon->id)->firstOrFail();
    app(MobileVoiceEndpointRegistrar::class)->markVoiceReady($device);

    MobileDevice::query()
        ->where('user_id', $landon->id)
        ->update(['voice_ready_at' => now()->subHours(12)]);

    $coverage = CommunicationsShopProjection::forCurrentShop()->resolve()['coverage'];
    $landonRow = collect($coverage)->first(fn ($row) => $row->name === 'Landon Carter');

    expect($landonRow)->not->toBeNull()
        ->and($landonRow->summary)->toBe('Offline');

    Carbon::setTestNow();
});
