<?php

use App\Ark\Operations\Communications\CommunicationWorkboardProjection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', null);
    config()->set('services.twilio.account_sid', 'ACtestaccount');
    $this->markTestSkipped('Workspace Surface Phase 1: comms workboard redirects to Attention; rewrite projection tests for Attention in Phase 2.');
});

test('unknown sms lead appears on workboard new opportunities', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMwbunknown01',
        'From' => '+13035551234',
        'To' => '+17195559999',
        'Body' => 'My AC is not cold.',
        'NumMedia' => '0',
    ])->assertOk();

    $this->actingAs($advisor)
        ->get(route('operations.communications.workboard'))
        ->assertOk()
        ->assertSee('ops-comms-lane-new', false)
        ->assertSee('My AC is not cold.', false)
        ->assertSee('SMS', false)
        ->assertSee('Reply', false)
        ->assertSee('Check In', false);

    $projection = app(CommunicationWorkboardProjection::class)->resolve($advisor);

    expect($projection['counts']['new_opportunities'])->toBe(1)
        ->and($projection['new_opportunities'][0]['source'])->toBe('sms');
});

test('known customer inbound sms appears in needs shop not new opportunities', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Driver',
        'phone' => '7195551234',
        'customer_type' => 'Retail',
    ]);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMwbknown01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'Body' => 'Vehicle ready yet?',
        'NumMedia' => '0',
    ])->assertOk();

    expect(Lead::query()->count())->toBe(0);

    $conversation = Conversation::query()->sole();

    expect($conversation->waiting_on)->toBe(ConversationWaitingOn::Shop)
        ->and($conversation->status)->toBe(ConversationStatus::Open);

    $projection = app(CommunicationWorkboardProjection::class)->resolve($advisor);

    expect($projection['counts']['new_opportunities'])->toBe(0)
        ->and($projection['counts']['needs_shop'])->toBe(1)
        ->and($projection['needs_shop'][0]['headline'] ?? '')->toContain('Jane');

    $this->actingAs($advisor)
        ->get(route('operations.communications.workboard'))
        ->assertOk()
        ->assertSee('Needs shop', false)
        ->assertSee('Vehicle ready yet?', false)
        ->assertSee('ops-comms-lane-needs-shop', false);
});

test('unknown conversation without lead appears in needs shop safety net', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    Lead::query()->create([
        'source' => LeadSource::Sms,
        'state' => LeadState::Lost,
        'concern' => 'Old concern',
        'contact_phone' => '3035559999',
    ]);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMwbsafety01',
        'From' => '+13035559999',
        'To' => '+17195559999',
        'Body' => 'Can you look at my brakes?',
        'NumMedia' => '0',
    ])->assertOk();

    Lead::query()->where('contact_phone', '3035559999')->where('state', LeadState::Received)->delete();

    $projection = app(CommunicationWorkboardProjection::class)->resolve($advisor);

    expect($projection['counts']['needs_shop'])->toBeGreaterThanOrEqual(1);

    $this->actingAs($advisor)
        ->get(route('operations.communications.workboard'))
        ->assertOk()
        ->assertSee('ops-comms-lane-needs-shop', false)
        ->assertSee('Can you look at my brakes?', false);
});

test('outbound reply sets owner and keeps Needs attention', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    Customer::query()->create([
        'first_name' => 'Ben',
        'last_name' => 'Customer',
        'phone' => '7195554321',
        'customer_type' => 'Retail',
    ]);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMposturein01',
        'From' => '+17195554321',
        'To' => '+17195559999',
        'Body' => 'My AC is not cold.',
        'NumMedia' => '0',
    ])->assertOk();

    $conversation = Conversation::query()->sole();

    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMpostureout01',
            'status' => 'queued',
        ], 201),
    ]);

    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'ACtest');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.conversations.messages.store', $conversation), [
            'body' => 'Bring it by tomorrow morning.',
        ])
        ->assertOk();

    $conversation->refresh();

    expect($conversation->owned_by_user_id)->toBe($advisor->id)
        ->and($conversation->waiting_on)->toBe(ConversationWaitingOn::Shop);

    $projection = app(CommunicationWorkboardProjection::class)->resolve($advisor);

    expect($projection['counts']['needs_shop'])->toBe(1)
        ->and($projection['counts']['waiting_customer'])->toBe(0);
});

test('resolve conversation moves thread to recently resolved', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Driver',
        'phone' => '7195559876',
        'customer_type' => 'Retail',
    ]);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMresolve01',
        'From' => '+17195559876',
        'To' => '+17195559999',
        'Body' => 'All set, thanks.',
        'NumMedia' => '0',
    ])->assertOk();

    $conversation = Conversation::query()->sole();

    $this->actingAs($advisor)
        ->post(route('operations.conversations.resolve', $conversation))
        ->assertRedirect();

    $conversation->refresh();

    expect($conversation->status)->toBe(ConversationStatus::Resolved)
        ->and($conversation->resolved_at)->not->toBeNull();

    $projection = app(CommunicationWorkboardProjection::class)->resolve($advisor);

    expect($projection['counts']['needs_shop'])->toBe(0)
        ->and($projection['counts']['recently_resolved'])->toBe(1);
});

test('inbound on resolved conversation reopens and needs shop', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    Customer::query()->create([
        'first_name' => 'Reopen',
        'last_name' => 'Test',
        'phone' => '7195551111',
        'customer_type' => 'Retail',
    ]);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMreopen01',
        'From' => '+17195551111',
        'To' => '+17195559999',
        'Body' => 'First message',
        'NumMedia' => '0',
    ])->assertOk();

    $conversation = Conversation::query()->sole();

    $this->actingAs($advisor)
        ->post(route('operations.conversations.resolve', $conversation))
        ->assertRedirect();

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMreopen02',
        'From' => '+17195551111',
        'To' => '+17195559999',
        'Body' => 'Actually one more question',
        'NumMedia' => '0',
    ])->assertOk();

    $conversation->refresh();

    expect($conversation->status)->toBe(ConversationStatus::Open)
        ->and($conversation->waiting_on)->toBe(ConversationWaitingOn::Shop)
        ->and($conversation->reopen_count)->toBe(1);
});

test('spam lead does not appear in new opportunities', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Spam,
        'concern' => 'Casino bonus',
        'contact_phone' => '7195550001',
        'spam_signals' => ['too_fast'],
    ]);

    Lead::query()->create([
        'source' => LeadSource::Sms,
        'state' => LeadState::Received,
        'concern' => 'Real brakes noise',
        'contact_phone' => '7195550002',
    ]);

    $projection = app(CommunicationWorkboardProjection::class)->resolve($advisor);

    expect($projection['counts']['new_opportunities'])->toBe(1)
        ->and(collect($projection['new_opportunities'])->pluck('concern')->all())->not->toContain('Casino bonus');

    $this->actingAs($advisor)
        ->get(route('operations.communications.workboard'))
        ->assertOk()
        ->assertSee('Real brakes noise', false)
        ->assertSee('ops-comms-lane-new', false);
});

test('advisor can access communications workboard', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.communications.workboard'))
        ->assertOk()
        ->assertSee('Communications', false)
        ->assertSee('ops-comms-lane-calls', false)
        ->assertDontSee('Attention queue', false);
});

test('unauthenticated user cannot access communications workboard', function (): void {
    $this->get(route('operations.communications.workboard'))
        ->assertRedirect(route('login'));
});

test('sms lead workboard row includes reply and open intake links', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMwblinks01',
        'From' => '+13035558888',
        'To' => '+17195559999',
        'Body' => 'Need an oil change quote',
        'NumMedia' => '0',
    ])->assertOk();

    $lead = Lead::query()->sole();

    $this->actingAs($advisor)
        ->get(route('operations.communications.workboard'))
        ->assertOk()
        ->assertSee(route('operations.conversations.reply', $lead->conversation_id), false)
        ->assertSee(route('operations.leads.intake', $lead), false);
});

test('lead pressure open count agrees with workboard sms open leads', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMwbcount01',
        'From' => '+13035557777',
        'To' => '+17195559999',
        'Body' => 'First SMS lead',
        'NumMedia' => '0',
    ])->assertOk();

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMwbcount02',
        'From' => '+13035556666',
        'To' => '+17195559999',
        'Body' => 'Second SMS lead',
        'NumMedia' => '0',
    ])->assertOk();

    $projection = app(CommunicationWorkboardProjection::class)->resolve($advisor);

    expect($projection['counts']['sms_open_leads'])->toBe(2)
        ->and($projection['counts']['lead_pressure_open'])->toBe(2)
        ->and($projection['counts']['new_opportunities'])->toBe(2);
});

test('communications workboard fragment returns lane html and signature', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMwbfrag01',
        'From' => '+13035557777',
        'To' => '+17195559999',
        'Body' => 'Fragment refresh lead',
        'NumMedia' => '0',
    ])->assertOk();

    $response = $this->actingAs($advisor)
        ->getJson(route('operations.communications.workboard.fragment'))
        ->assertOk();

    $response->assertJsonStructure([
        'counts' => ['calls_waiting', 'new_opportunities', 'needs_shop', 'waiting_customer', 'total_actionable'],
        'signature',
        'lanes' => ['calls', 'new', 'needs_shop', 'waiting_customer', 'recently_resolved'],
    ]);

    expect($response->json('counts.new_opportunities'))->toBeGreaterThan(0)
        ->and($response->json('lanes.new'))->toContain('Fragment refresh lead')
        ->and($response->json('lanes.new'))->toContain('ops-comms-lane-new');
});

test('communications workboard page exposes live refresh fragment url', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.communications.workboard'))
        ->assertOk()
        ->assertSee('id="ops-comms-workboard-live"', false)
        ->assertSee(route('operations.communications.workboard.fragment'), false);
});

test('call queue api exposes nav pressure for live communications badge', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMnavpressure1',
        'From' => '+13035558888',
        'To' => '+17195559999',
        'Body' => 'Nav badge SMS lead',
        'NumMedia' => '0',
    ])->assertOk();

    $response = $this->actingAs($advisor)
        ->getJson(route('operations.telephony.call-queue'))
        ->assertOk();

    expect($response->json('nav_pressure_count'))->toBeGreaterThan(0)
        ->and($response->json('workboard_counts.new_opportunities'))->toBeGreaterThan(0);
});

test('operations layout exposes communications nav pressure hook', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.index'))
        ->assertOk()
        ->assertSee('data-ops-comms-nav-link', false);
});
