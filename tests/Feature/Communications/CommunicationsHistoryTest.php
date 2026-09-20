<?php

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('advisor can open communications history workspace', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.communications.history'))
        ->assertOk()
        ->assertSee('History', false)
        ->assertSee('ops-comms-history-filters', false);
});

test('history defaults to the last 30 days without a search', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $recent = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CArecent001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195551111',
        'to_number' => '+17195559999',
        'normalized_from' => '7195551111',
        'status' => CallSessionStatus::Completed,
        'worked_at' => now()->subDays(2),
        'started_at' => now()->subDays(2),
    ]);

    $stale = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAstale001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195552222',
        'to_number' => '+17195559999',
        'normalized_from' => '7195552222',
        'status' => CallSessionStatus::Completed,
        'worked_at' => now()->subDays(45),
        'started_at' => now()->subDays(45),
    ]);

    // Row select URLs prove list membership — phone digits also appear in
    // topbar queue payloads, so they cannot distinguish the history list.
    $this->actingAs($advisor)
        ->get(route('operations.communications.history'))
        ->assertOk()
        ->assertSee('call='.$recent->id, false)
        ->assertDontSee('call='.$stale->id, false);
});

test('history finds old calls by phone search across all time', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Archive',
        'last_name' => 'Caller',
        'phone' => '7195554242',
    ]);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAarchive001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195554242',
        'to_number' => '+17195559999',
        'normalized_from' => '7195554242',
        'status' => CallSessionStatus::Completed,
        'customer_id' => $customer->id,
        'worked_at' => now()->subYear()->subDay(),
        'started_at' => now()->subYear(),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.history', ['q' => '7195554242']))
        ->assertOk()
        ->assertSee('Archive Caller', false)
        ->assertSee('(719) 555-4242', false);

    $this->actingAs($advisor)
        ->get(route('operations.communications.history', [
            'call' => $session->id,
            'q' => '7195554242',
        ]))
        ->assertOk()
        ->assertSee('Archive Caller', false)
        ->assertDontSee('Log call note', false);
});

test('history recorded filter and playback action surface for old calls', function (): void {
        
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CArecorded001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195554343',
        'to_number' => '+17195559999',
        'normalized_from' => '7195554343',
        'status' => CallSessionStatus::Completed,
        'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/ACtestaccount/Recordings/REarchive001',
        'started_at' => now()->subMonths(14),
        'worked_at' => now()->subMonths(14),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.history', [
            'media' => 'recorded',
            'from' => now()->subYears(2)->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertSee('Recording on file', false);

    $this->actingAs($advisor)
        ->get(route('operations.communications.history', [
            'call' => $session->id,
            'media' => 'recorded',
            'from' => now()->subYears(2)->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertSee('Play recording', false);
});

test('history phone search also surfaces matching sms conversations', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '7195554545',
        'status' => ConversationStatus::Resolved,
        'updated_at' => now()->subMonths(8),
    ]);

    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAdifferent001',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195559999',
        'to_number' => '+17195558888',
        'normalized_from' => '7195559999',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMonth(),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.history', ['q' => '7195554545']))
        ->assertOk()
        ->assertSee('(719) 555-4545', false)
        ->assertSee(route('operations.communications.history', ['conversation' => $conversation->id]), false);
});

test('technician cannot open communications history', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $this->actingAs($technician)
        ->get(route('operations.communications.history'))
        ->assertForbidden();
});
