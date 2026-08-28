<?php

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', null);
    config()->set('services.twilio.account_sid', 'ACtestaccount');
});

test('unknown inbound sms reconciles an open lead', function (): void {
    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMleadunknown01',
        'From' => '+13039056841',
        'To' => '+17195559999',
        'Body' => 'Are you open on the weekends?',
        'NumMedia' => '0',
    ])->assertOk();

    $lead = Lead::query()->sole();

    expect($lead->source)->toBe(LeadSource::Sms)
        ->and($lead->state)->toBe(LeadState::Received)
        ->and($lead->concern)->toBe('Are you open on the weekends?')
        ->and($lead->contact_phone)->toBe('3039056841')
        ->and($lead->conversation_id)->toBe(Conversation::query()->sole()->id);

    $message = ConversationMessage::query()->sole();

    expect($lead->metadata['ingress_conversation_message_id'] ?? null)->toBe($message->id);
});

test('known customer inbound sms does not create a lead', function (): void {
    \App\Ark\Operations\Customers\Customer::query()->create([
        'first_name' => 'Jane',
        'last_name' => 'Driver',
        'phone' => '7195551234',
        'customer_type' => 'Retail',
    ]);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMleadknown01',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'Body' => 'Vehicle is ready?',
        'NumMedia' => '0',
    ])->assertOk();

    expect(Lead::query()->count())->toBe(0);
});

test('distinct concerns from same unknown phone open separate leads', function (): void {
    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMleadconcern01',
        'From' => '+15550100999',
        'To' => '+17195559999',
        'Body' => 'My AC is not cold.',
        'NumMedia' => '0',
    ])->assertOk();

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMleadconcern02',
        'From' => '+15550100999',
        'To' => '+17195559999',
        'Body' => 'My brakes are grinding.',
        'NumMedia' => '0',
    ])->assertOk();

    $concerns = Lead::query()->orderBy('id')->pluck('concern')->all();

    expect($concerns)->toBe([
        'My AC is not cold.',
        'My brakes are grinding.',
    ])
        ->and(Lead::query()->where('contact_phone', '5550100999')->count())->toBe(2);
});

test('duplicate webhook delivery does not create duplicate leads', function (): void {
    $payload = [
        'MessageSid' => 'SMleaddupe01',
        'From' => '+15550108888',
        'To' => '+17195559999',
        'Body' => 'How much for brakes on my Jeep?',
        'NumMedia' => '0',
    ];

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), $payload)->assertOk();
    $this->post(route('webhooks.communications.twilio.messaging.incoming'), $payload)->assertOk();

    expect(Lead::query()->count())->toBe(1)
        ->and(ConversationMessage::query()->count())->toBe(1);
});

test('sms lead appears on communications needs attention', function (): void {
    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMleadindex01',
        'From' => '+13039056841',
        'To' => '+17195559999',
        'Body' => 'Are you open on the weekends?',
        'NumMedia' => '0',
    ])->assertOk();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(\App\Ark\Operations\Communications\CommunicationsNeedsYou::url())
        ->assertOk()
        ->assertSee('Are you open on the weekends?');
});

test('matching open concern from same unknown phone reuses lead instead of duplicating', function (): void {
    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMleadmatch01',
        'From' => '+15550107777',
        'To' => '+17195559999',
        'Body' => 'Are you open Saturday?',
        'NumMedia' => '0',
    ])->assertOk();

    $firstLeadId = Lead::query()->sole()->id;

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMleadmatch02',
        'From' => '+15550107777',
        'To' => '+17195559999',
        'Body' => 'Are you open Saturday?',
        'NumMedia' => '0',
    ])->assertOk();

    expect(Lead::query()->count())->toBe(1)
        ->and(Lead::query()->sole()->id)->toBe($firstLeadId);
});

test('acknowledgment sms reuses latest open lead for same phone', function (): void {
    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMleadack01',
        'From' => '+18086660908',
        'To' => '+17195559999',
        'Body' => 'Hello - My name is Art and I need a pre-purchase inspection on a Honda Element.',
        'NumMedia' => '0',
    ])->assertOk();

    $firstLeadId = Lead::query()->sole()->id;

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMleadack02',
        'From' => '+18086660908',
        'To' => '+17195559999',
        'Body' => 'Ok thank you',
        'NumMedia' => '0',
    ])->assertOk();

    expect(Lead::query()->count())->toBe(1)
        ->and(Lead::query()->sole()->id)->toBe($firstLeadId);
});

test('sms follow up on same conversation reuses open website lead for same phone', function (): void {
    $recorder = app(\App\Ark\Operations\Leads\LeadRecorder::class);

    $websiteLead = $recorder->recordWebsiteSubmission([
        'concern' => 'Looking to buy a 2010 Honda Element from a private owner and need a mechanic inspection.',
        'contact_phone' => '8086660908',
        'contact_name' => 'Art',
        'contact_email' => 'candelasart@gmail.com',
        'vehicle_year' => 2010,
        'vehicle_make' => 'Honda',
        'vehicle_model' => 'Element',
    ]);

    $this->post(route('webhooks.communications.twilio.messaging.incoming'), [
        'MessageSid' => 'SMleadweb01',
        'From' => '+18086660908',
        'To' => '+17195559999',
        'Body' => 'Hello - My name is Art Candelas and I am purchasing a used Honda Element from a private owner. Can you inspect it?',
        'NumMedia' => '0',
    ])->assertOk();

    expect(Lead::query()->count())->toBe(1)
        ->and(Lead::query()->sole()->id)->toBe($websiteLead->id)
        ->and(Lead::query()->sole()->contact_name)->toBe('Art');
});
