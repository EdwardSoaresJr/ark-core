<?php

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
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

test('communications workspace shows outbound dialed number and active status for live calls', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Marc',
        'last_name' => 'Babcock',
        'phone' => '8005551212',
    ]);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAoutbound001',
        'direction' => CallSessionDirection::Outbound,
        'from_number' => 'sip:desk1@example.sip.us1.twilio.com',
        'to_number' => '+18005551212',
        'normalized_from' => '7195550000',
        'normalized_to' => '8005551212',
        'status' => CallSessionStatus::Answered,
        'customer_id' => $customer->id,
        'owned_by_user_id' => $advisor->id,
        'started_at' => now()->subMinute(),
        'answered_at' => now()->subSeconds(30),
    ]);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['call' => $session->id]))
        ->assertOk()
        ->assertSee('Marc Babcock', false)
        ->assertSee('(800) 555-1212', false)
        ->assertSee('Active', false)
        ->assertSee('To', false)
        ->assertDontSee('sip:desk1@example.sip.us1.twilio.com', false);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['call' => $session->id]))
        ->assertOk()
        ->assertSee('(800) 555-1212', false)
        ->assertSee('Active', false)
        ->assertDontSee('sip:desk1@example.sip.us1.twilio.com', false);
});

test('communications workspace fragment returns updated outbound call signature', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAoutbound002',
        'direction' => CallSessionDirection::Outbound,
        'from_number' => 'sip:desk1@example.sip.us1.twilio.com',
        'to_number' => '+18005559999',
        'normalized_from' => '7195550000',
        'normalized_to' => '8005559999',
        'status' => CallSessionStatus::Ringing,
        'started_at' => now(),
    ]);

    $ringing = $this->actingAs($advisor)
        ->getJson(route('operations.communications.workspace.fragment', [
            'section' => 'inbox',
            'call' => $session->id,
        ]))
        ->assertOk()
        ->json();

    expect($ringing['signature'] ?? '')->not->toBe('')
        ->and($ringing['thread'] ?? '')->toContain('Ringing');

    $session->update(['status' => CallSessionStatus::Answered, 'answered_at' => now()]);

    $answered = $this->actingAs($advisor)
        ->getJson(route('operations.communications.workspace.fragment', [
            'section' => 'inbox',
            'call' => $session->id,
        ]))
        ->assertOk()
        ->json();

    expect($answered['signature'] ?? '')->not->toBe($ringing['signature'])
        ->and($answered['thread'] ?? '')->toContain('Active')
        ->and($answered['context'] ?? '')->toContain('(800) 555-9999');
});

test('handled call stays in inbox with full thread including outbound messages', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $customer = Customer::query()->create([
        'first_name' => 'Marc',
        'last_name' => 'Babcock',
        'phone' => '8005551212',
    ]);

    $session = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAhandledout001',
        'direction' => CallSessionDirection::Outbound,
        'from_number' => 'sip:desk1@example.sip.us1.twilio.com',
        'to_number' => '+18005551212',
        'normalized_from' => '7195550000',
        'normalized_to' => '8005551212',
        'status' => CallSessionStatus::Completed,
        'customer_id' => $customer->id,
        'owned_by_user_id' => $advisor->id,
        'started_at' => now()->subMinutes(30),
        'answered_at' => now()->subMinutes(29),
        'ended_at' => now()->subMinutes(20),
        'worked_at' => now()->subMinutes(15),
    ]);

    // Creating the CallSession already synced a conversation for this phone.
    $conversation = Conversation::query()
        ->where('contact_surface', ConversationContactSurface::Phone)
        ->where('contact_address', '8005551212')
        ->firstOrFail();

    $conversation->update([
        'customer_id' => $customer->id,
        'status' => ConversationStatus::Open,
    ]);

    $participant = ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Advisor,
        'user_id' => $advisor->id,
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'channel' => OperationalCommunicationChannel::Sms,
        'direction' => OperationalCommunicationDirection::Outbound,
        'body' => 'Following up on our call — estimate link is on the way.',
        'occurred_at' => now()->subMinutes(10),
    ]);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['call' => $session->id]))
        ->assertOk()
        ->assertSee('Handled', false)
        ->assertSee('Outgoing call', false)
        ->assertSee('Following up on our call — estimate link is on the way.', false);
});
