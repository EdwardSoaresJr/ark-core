<?php

use App\Ark\Operations\Communications\CommunicationsWorkspaceProjection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\IncomingCallPayload;
use App\Ark\Operations\Telephony\ProcessIncomingCallAction;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Telephony\TelephonyExtensionDeviceType;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('communications inbox excludes feature code call sessions', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customerCall = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'asterisk-customer-1',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '101',
        'to_number' => '7195551234',
        'normalized_from' => '101',
        'normalized_to' => '7195551234',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinute(),
    ]);

    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'asterisk-echo-test',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '101',
        'to_number' => '*43',
        'normalized_from' => '101',
        'normalized_to' => '*43',
        'status' => CallSessionStatus::Completed,
        'started_at' => now(),
    ]);

    $workspace = app(CommunicationsWorkspaceProjection::class)->inbox($advisor, null, null, null);

    $callKeys = collect($workspace['list_items'])
        ->where('kind', 'call')
        ->pluck('key')
        ->all();

    expect($callKeys)->toContain('call:'.$customerCall->id)
        ->not->toContain('call:'.CallSession::query()->where('provider_call_sid', 'asterisk-echo-test')->value('id'));
});

test('communications inbox threads one row per customer when conversation and calls overlap', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Edward',
        'last_name' => 'Soares',
        'phone' => '7195550199',
    ]);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195550199',
        'status' => ConversationStatus::Open,
    ]);

    foreach (range(1, 3) as $index) {
        CallSession::query()->create([
            'provider' => TelephonyProviderType::Twilio,
            'provider_call_sid' => 'asterisk-edward-'.$index,
            'direction' => CallSessionDirection::Inbound,
            'from_number' => '7195550199',
            'to_number' => '101',
            'normalized_from' => '7195550199',
            'normalized_to' => '101',
            'customer_id' => $customer->id,
            'status' => CallSessionStatus::Completed,
            'started_at' => now()->subHours($index),
        ]);
    }

    $workspace = app(CommunicationsWorkspaceProjection::class)->inbox($advisor, null, null, null);

    $edwardRows = collect($workspace['list_items'])
        ->filter(fn (array $item): bool => ($item['headline'] ?? '') === 'Alex Rivera')
        ->values();

    expect($edwardRows)->toHaveCount(1)
        ->and($edwardRows->first()['kind'])->toBe('conversation')
        ->and($edwardRows->first()['key'])->toBe('conversation:'.$conversation->id);
});

test('communications inbox excludes extension leg call sessions', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    TelephonyExtension::query()->create([
        'extension' => '101',
        'display_name' => 'Front Counter',
        'device_type' => TelephonyExtensionDeviceType::DeskPhone,
        'enabled' => true,
    ]);

    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'asterisk-ext-leg',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '101',
        'to_number' => '101',
        'normalized_from' => '101',
        'normalized_to' => '101',
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    $workspace = app(CommunicationsWorkspaceProjection::class)->inbox($advisor, null, null, null);

    $callKeys = collect($workspace['list_items'])->pluck('key')->all();

    expect($callKeys)->not->toContain('call:'.CallSession::query()->where('provider_call_sid', 'asterisk-ext-leg')->value('id'));
});

test('communications inbox excludes unknown caller desk ring legs', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    TelephonyExtension::query()->create([
        'extension' => '101',
        'display_name' => 'Front Counter',
        'device_type' => TelephonyExtensionDeviceType::DeskPhone,
        'enabled' => true,
    ]);

    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'asterisk-unknown-to-101',
        'direction' => CallSessionDirection::Outbound,
        'from_number' => '<unknown>',
        'to_number' => '101',
        'normalized_from' => '',
        'normalized_to' => '101',
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    $workspace = app(CommunicationsWorkspaceProjection::class)->inbox($advisor, null, null, null);

    $callKeys = collect($workspace['list_items'])->pluck('key')->all();

    expect($callKeys)->not->toContain('call:'.CallSession::query()->where('provider_call_sid', 'asterisk-unknown-to-101')->value('id'));
});

test('communications inbox excludes desk companion legs to customer phone', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    TelephonyExtension::query()->create([
        'extension' => '101',
        'display_name' => 'Front Counter',
        'device_type' => TelephonyExtensionDeviceType::DeskPhone,
        'enabled' => true,
    ]);

    $companion = CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'asterisk-desk-companion',
        'direction' => CallSessionDirection::Outbound,
        'from_number' => '101',
        'to_number' => '7195550199',
        'normalized_from' => '101',
        'normalized_to' => '7195550199',
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    $workspace = app(CommunicationsWorkspaceProjection::class)->inbox($advisor, null, null, null);

    expect(collect($workspace['list_items'])->pluck('key')->all())
        ->not->toContain('call:'.$companion->id);
});

test('communications inbox conversation thread includes customer calls', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Edward',
        'last_name' => 'Soares',
        'phone' => '7195550199',
    ]);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195550199',
        'status' => ConversationStatus::Open,
    ]);

    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'asterisk-edward-call',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '7195550199',
        'to_number' => '101',
        'normalized_from' => '7195550199',
        'normalized_to' => '101',
        'customer_id' => $customer->id,
        'status' => CallSessionStatus::Missed,
        'started_at' => now()->subHour(),
    ]);

    $workspace = app(CommunicationsWorkspaceProjection::class)->inbox(
        $advisor,
        $conversation->id,
        null,
        null,
    );

    $events = collect($workspace['thread']['events'] ?? []);

    expect($events->contains(
        fn ($event): bool => $event instanceof OperationalEventEntry
            && in_array($event->kind->value, ['call', 'missed_call', 'voicemail'], true),
    ))->toBeTrue()
        ->and($workspace['thread']['events'][0]->headline)->toContain('call');
});

test('communications inbox call thread renders recording player when available', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

        
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195550199',
        'status' => ConversationStatus::Open,
    ]);

    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'asterisk-edward-recording',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '7195550199',
        'to_number' => '101',
        'normalized_from' => '7195550199',
        'normalized_to' => '101',
        'status' => CallSessionStatus::Completed,
        'answered_at' => now()->subMinutes(5),
        'ended_at' => now()->subMinutes(4),
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/ACtestaccount/Recordings/REinbox001',
        'started_at' => now()->subMinutes(5),
    ]);

    $html = view('operations.communications.workspace.inbox', [
        'workspace' => app(CommunicationsWorkspaceProjection::class)->inbox(
            $advisor,
            $conversation->id,
            null,
            null,
        ),
    ])->render();

    expect($html)->toContain('Incoming call')
        ->and($html)->toContain('Recording')
        ->and(
            str_contains($html, 'ops-comms-workspace__call-audio')
            || str_contains($html, 'Recording on file')
        )->toBeTrue();
});

test('incoming feature code events do not create call sessions', function (): void {
    $payload = new IncomingCallPayload(
        provider: TelephonyProviderType::Twilio,
        providerCallSid: 'twilio-fc-ignored',
        fromNumber: '101',
        toNumber: '*43',
        normalizedFrom: '101',
        normalizedTo: '*43',
        status: CallSessionStatus::Ringing,
        rawPayload: [
            'asterisk_event' => 'ringing',
            'asterisk_called_extension' => '*43',
        ],
    );

    $result = app(ProcessIncomingCallAction::class)->execute($payload);

    expect($result['session'])->toBeNull()
        ->and($result['created'])->toBeFalse()
        ->and(CallSession::query()->where('provider_call_sid', 'twilio-fc-ignored')->exists())->toBeFalse();
});
