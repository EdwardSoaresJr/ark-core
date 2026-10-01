<?php

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\CleanupExtensionLegCallSessions;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Telephony\TelephonyExtensionDeviceType;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('cleanup extension leg command dry run lists phantom sessions', function (): void {
    TelephonyExtension::query()->create([
        'extension' => '101',
        'display_name' => 'Front Counter',
        'device_type' => TelephonyExtensionDeviceType::DeskPhone,
        'enabled' => true,
    ]);

    $phantom = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'phantom-101-leg',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '101',
        'to_number' => '101',
        'normalized_from' => '101',
        'normalized_to' => '101',
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'real-customer-call',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '7195550199',
        'to_number' => '101',
        'normalized_from' => '7195550199',
        'normalized_to' => '101',
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    $this->artisan('comms:cleanup-extension-leg-sessions')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(CallSession::query()->find($phantom->id))->not->toBeNull();
});

test('cleanup extension leg command force deletes phantom sessions only', function (): void {
    TelephonyExtension::query()->create([
        'extension' => '101',
        'display_name' => 'Front Counter',
        'device_type' => TelephonyExtensionDeviceType::DeskPhone,
        'enabled' => true,
    ]);

    $phantom = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'phantom-101-leg-delete',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '101',
        'to_number' => '101',
        'normalized_from' => '101',
        'normalized_to' => '101',
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    $customerCall = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'real-customer-call-delete',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '7195550199',
        'to_number' => '101',
        'normalized_from' => '7195550199',
        'normalized_to' => '101',
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    $deleted = app(CleanupExtensionLegCallSessions::class)->delete(dryRun: false);

    expect($deleted)->toBe([$phantom->id])
        ->and(CallSession::query()->find($phantom->id))->toBeNull()
        ->and(CallSession::query()->find($customerCall->id))->not->toBeNull();
});

test('cleanup extension leg command deletes unknown caller desk ring sessions', function (): void {
    TelephonyExtension::query()->create([
        'extension' => '101',
        'display_name' => 'Front Counter',
        'device_type' => TelephonyExtensionDeviceType::DeskPhone,
        'enabled' => true,
    ]);

    $phantom = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'unknown-to-101',
        'direction' => CallSessionDirection::Outbound,
        'from_number' => '<unknown>',
        'to_number' => '101',
        'normalized_from' => '',
        'normalized_to' => '101',
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    $deleted = app(CleanupExtensionLegCallSessions::class)->delete(dryRun: false);

    expect($deleted)->toBe([$phantom->id]);
});

test('cleanup extension leg command deletes outbound extension to extension sessions', function (): void {
    TelephonyExtension::query()->create([
        'extension' => '101',
        'display_name' => 'Front Counter',
        'device_type' => TelephonyExtensionDeviceType::DeskPhone,
        'enabled' => true,
    ]);

    $phantom = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'outbound-101-to-101',
        'direction' => CallSessionDirection::Outbound,
        'from_number' => '101',
        'to_number' => '101',
        'normalized_from' => '101',
        'normalized_to' => '101',
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    $customerLeg = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'outbound-101-to-customer',
        'direction' => CallSessionDirection::Outbound,
        'from_number' => '101',
        'to_number' => '7195550199',
        'normalized_from' => '101',
        'normalized_to' => '7195550199',
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    $deleted = app(CleanupExtensionLegCallSessions::class)->delete(dryRun: false);

    expect($deleted)->toContain($phantom->id, $customerLeg->id)
        ->and(CallSession::query()->find($phantom->id))->toBeNull()
        ->and(CallSession::query()->find($customerLeg->id))->toBeNull();
});
