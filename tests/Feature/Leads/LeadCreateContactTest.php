<?php

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\CallSession;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
});

test('advisor can create a customer contact from an sms lead', function (): void {
    $conversation = Conversation::query()->create([
        'contact_surface' => \App\Ark\Operations\Conversations\ConversationContactSurface::Phone,
        'contact_address' => '7196898483',
        'status' => \App\Ark\Operations\Conversations\ConversationStatus::Open,
    ]);

    $lead = Lead::query()->create([
        'source' => LeadSource::Sms,
        'state' => LeadState::Received,
        'concern' => 'Hi! This is your advisor Felipe...',
        'contact_phone' => '7196898483',
        'contact_preference' => LeadContactPreference::Text,
        'conversation_id' => $conversation->id,
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.leads.create-contact', $lead))
        ->assertOk()
        ->assertSee('Create contact from SMS lead', false)
        ->assertSee('(719) 689-8483', false);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->post(route('operations.leads.create-contact.store', $lead), [
            'first_name' => 'Felipe',
            'last_name' => 'Advisor',
            'phone' => '7196898483',
            'email' => 'felipe@example.test',
            'contact_preference' => LeadContactPreference::Text->value,
            'referral_source' => 'sms',
            'customer_type' => 'Retail',
        ])
        ->assertRedirect(CommunicationsNeedsYou::url(['conversation' => $conversation->id]))
        ->assertSessionHas('status');

    $lead->refresh();
    $customer = Customer::query()->where('phone', '7196898483')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->name)->toBe('Felipe Advisor')
        ->and($lead->customer_id)->toBe($customer->id)
        ->and(ConversationLink::query()->where('conversation_id', $conversation->id)->where('linkable_id', $customer->id)->exists())->toBeTrue();
});

test('create contact from ingress links an open lead and call session by phone', function (): void {
    $conversation = Conversation::query()->create([
        'contact_surface' => \App\Ark\Operations\Conversations\ConversationContactSurface::Phone,
        'contact_address' => '7195550199',
        'status' => \App\Ark\Operations\Conversations\ConversationStatus::Open,
    ]);

    $lead = Lead::query()->create([
        'source' => LeadSource::Call,
        'state' => LeadState::Received,
        'concern' => 'Missed call follow-up',
        'contact_phone' => '7195550199',
        'conversation_id' => $conversation->id,
    ]);

    $callSession = CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAtestcreatecontact01',
        'from_number' => '+17195550199',
        'to_number' => '+17195559999',
        'normalized_from' => '7195550199',
        'direction' => \App\Ark\Operations\Telephony\CallSessionDirection::Inbound,
        'status' => \App\Ark\Operations\Telephony\CallSessionStatus::Completed,
        'started_at' => now(),
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->post(route('operations.ingress.create-contact.store'), [
            'first_name' => 'Jordan',
            'last_name' => 'Lee',
            'phone' => '7195550199',
            'contact_preference' => LeadContactPreference::Call->value,
            'referral_source' => 'phone',
            'customer_type' => 'Retail',
            'conversation_id' => $conversation->id,
            'call_session_id' => $callSession->id,
        ])
        ->assertRedirect(route('operations.customers.show', Customer::query()->where('phone', '7195550199')->first()));

    $lead->refresh();
    $callSession->refresh();
    $customer = Customer::query()->where('phone', '7195550199')->first();

    expect($customer)->not->toBeNull()
        ->and($lead->customer_id)->toBe($customer->id)
        ->and($callSession->customer_id)->toBe($customer->id);
});

test('create contact links to existing customer when phone already matches', function (): void {
    $existing = Customer::query()->create([
        'first_name' => 'Existing',
        'last_name' => 'Customer',
        'phone' => '7195550100',
        'customer_type' => 'Retail',
    ]);

    $lead = Lead::query()->create([
        'source' => LeadSource::Sms,
        'state' => LeadState::Received,
        'concern' => 'Need an oil change',
        'contact_phone' => '7195550100',
    ]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->post(route('operations.leads.create-contact.store', $lead), [
            'first_name' => 'Should',
            'last_name' => 'Ignore',
            'phone' => '7195550100',
            'customer_type' => 'Retail',
        ])
        ->assertRedirect(CommunicationsNeedsYou::url());

    $lead->refresh();

    expect($lead->customer_id)->toBe($existing->id)
        ->and(Customer::query()->where('phone', '7195550100')->count())->toBe(1);
});
