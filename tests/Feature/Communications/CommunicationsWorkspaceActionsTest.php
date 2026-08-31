<?php

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
    Http::fake();
        });

test('advisor can add internal note from communications inbox', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $conversation = workspaceConversationFixture('7195558080');

    $this->actingAs($advisor)
        ->post(route('operations.communications.conversations.internal-note', $conversation), [
            'body' => 'Customer asked about brake noise — follow up tomorrow.',
            'section' => 'inbox',
        ])
        ->assertRedirect(CommunicationsNeedsYou::url(['conversation' => $conversation->id]))
        ->assertSessionHas('status', 'Internal note added.');

    $message = ConversationMessage::query()->sole();

    expect($message->conversation_id)->toBe($conversation->id)
        ->and($message->channel)->toBe(OperationalCommunicationChannel::Internal)
        ->and($message->direction)->toBe(OperationalCommunicationDirection::Internal)
        ->and($message->body)->toBe('Customer asked about brake noise — follow up tomorrow.');

    Http::assertNothingSent();
});

test('advisor can assign conversation to self from workspace', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $conversation = workspaceConversationFixture('7195558081');

    expect($conversation->owned_by_user_id)->toBeNull();

    $this->actingAs($advisor)
        ->post(route('operations.communications.conversations.assign', $conversation), [
            'assign_to' => 'me',
            'section' => 'inbox',
        ])
        ->assertRedirect(CommunicationsNeedsYou::url(['conversation' => $conversation->id]))
        ->assertSessionHas('status');

    expect($conversation->refresh()->owned_by_user_id)->toBe($advisor->id);
});

test('advisor can reopen resolved conversation from workspace', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $conversation = workspaceConversationFixture('7195558082', ConversationStatus::Resolved);

    $this->actingAs($advisor)
        ->post(route('operations.communications.conversations.reopen', $conversation), [
            'section' => 'attention',
        ])
        ->assertRedirect(CommunicationsNeedsYou::url(['conversation' => $conversation->id]))
        ->assertSessionHas('status', 'Conversation reopened.');

    expect($conversation->refresh()->status)->toBe(ConversationStatus::Open);
});

test('advisor can log call note against phone conversation', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAworkspacenote01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195558083',
        'to_number' => '+17195559999',
        'normalized_from' => '7195558083',
        'status' => CallSessionStatus::Completed,
        'started_at' => now()->subMinutes(5),
        'answered_at' => now()->subMinutes(4),
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.communications.calls.note', $session), [
            'body' => 'Voicemail — wants oil change quote.',
        ])
        ->assertRedirect(CommunicationsNeedsYou::url(['call' => $session->id]))
        ->assertSessionHas('status', 'Call note logged.');

    $conversation = Conversation::query()->where('contact_address', '7195558083')->sole();
    $message = ConversationMessage::query()->where('conversation_id', $conversation->id)->sole();

    expect($message->direction)->toBe(OperationalCommunicationDirection::Internal)
        ->and($message->metadata['call_note'] ?? false)->toBeTrue()
        ->and($message->metadata['call_session_id'] ?? null)->toBe($session->id)
        ->and($message->body)->toBe('Voicemail — wants oil change quote.');

    Http::assertNothingSent();
});

test('communications inbox shows send estimate when customer is linked', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Comms',
        'last_name' => 'Customer',
        'phone' => '7195558099',
        'email' => 'comms.customer@example.test',
    ]);
    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Subaru',
        'model' => 'Forester',
        'vin' => 'JF2SKAEC4KH123456',
        'normalized_vin' => 'JF2SKAEC4KH123456',
    ]);
    $repairOrder = \App\Ark\Operations\RepairOrders\RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => \App\Ark\Operations\RepairOrders\RepairOrderStatus::Estimate,
        'concern_summary' => 'Brake noise',
    ]);
    $concern = \App\Ark\Operations\RepairOrders\RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes',
        'disposition' => \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);
    \App\Ark\Operations\RepairOrders\RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => \App\Ark\Operations\RepairOrders\RepairOrderLineType::Labor,
        'description' => 'Brake inspection',
        'quantity' => '1.00',
        'unit_price_cents' => 12000,
    ]);
    $conversation = workspaceConversationFixture('7195558099');

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('Send Estimate', false);
});

test('marking a call handled returns to the list without re-selecting the cleared row', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAmarkhandled002',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195558091',
        'to_number' => '+17195559999',
        'normalized_from' => '7195558091',
        'status' => CallSessionStatus::Missed,
        'started_at' => now()->subMinutes(3),
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.communications.calls.mark-handled', $session), [
            'section' => 'inbox',
        ])
        ->assertRedirect(route('operations.communications.inbox', ['filter' => 'needs']))
        ->assertSessionHas('status', 'Call marked handled.');

    expect($session->refresh()->worked_at)->not->toBeNull();
});

test('technician cannot use communications workspace action routes', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $conversation = workspaceConversationFixture('7195558084');

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAworkspacedeny01',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195558084',
        'to_number' => '+17195559999',
        'normalized_from' => '7195558084',
        'status' => CallSessionStatus::Missed,
        'started_at' => now(),
    ]);

    $this->actingAs($technician)
        ->post(route('operations.communications.conversations.internal-note', $conversation), ['body' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($technician)
        ->post(route('operations.communications.conversations.assign', $conversation), ['assign_to' => 'me'])
        ->assertForbidden();

    $this->actingAs($technician)
        ->post(route('operations.communications.conversations.reopen', $conversation))
        ->assertForbidden();

    $this->actingAs($technician)
        ->post(route('operations.communications.conversations.mark-handled', $conversation))
        ->assertForbidden();

    $this->actingAs($technician)
        ->post(route('operations.communications.calls.note', $session), ['body' => 'Nope'])
        ->assertForbidden();
});

function workspaceConversationFixture(string $phone, ConversationStatus $status = ConversationStatus::Open): Conversation
{
    return Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => $phone,
        'status' => $status,
    ]);
}
