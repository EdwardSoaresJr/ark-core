<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionMediaCaptureStatus;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('advisor can open dedicated calls and voicemail library', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.communications.calls'))
        ->assertOk()
        ->assertSee('Calls &amp; Voicemail', false)
        ->assertSee('Calls &amp; VM', false);
});

test('calls library shows inline voicemail player for sessions with voicemail', function (): void {
    ShopSettings::current()->update([
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Voicemail',
        'last_name' => 'Tester',
        'phone' => '7195556767',
    ]);

    // SID must be RE + hex — the media URI parser rejects non-Twilio-shaped SIDs.
    $recordingUrl = 'https://api.twilio.com/2010-04-01/Accounts/ACtest/Recordings/RE0123456789abcdef0123456789abcdef';

    $session = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio->value,
        'provider_call_sid' => 'CA-voicemail-test',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195556767',
        'to_number' => '+17194136227',
        'normalized_from' => '7195556767',
        'status' => CallSessionStatus::Missed,
        'customer_id' => $customer->id,
        'voicemail_url' => $recordingUrl,
        'voicemail_capture_status' => CallSessionMediaCaptureStatus::Available,
        'started_at' => now()->subHour(),
    ]);

    $playbackUrl = route('operations.telephony.call-sessions.recording', [
        'callSession' => $session,
        'kind' => 'voicemail',
    ]);

    $this->actingAs($advisor)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.communications.calls', ['filter' => 'voicemail']))
        ->assertOk()
        ->assertSee('Voicemail Tester', false)
        ->assertSee('Text', false)
        ->assertSee(route('operations.customers.show', $customer).'?compose=text#customer-communication', false)
        ->assertSee('<audio controls preload="none" class="ops-call-library__audio" src="'.$playbackUrl.'">', false);
});
