<?php

use App\Ark\Operations\Communications\Events\CommsInterruptReceived;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\WebsiteLeadInterruptBroadcaster;
use App\Ark\Operations\Leads\WebsiteLeadInterruptDismissal;
use App\Ark\Operations\Leads\WebsiteLeadInterruptPresenter;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');

    $this->seed(ArkAuthorizationSeeder::class);

    ShopSettings::current()->update([
        'learn_training_gate_enabled' => false,
        'telephony_call_flow' => array_merge(
            ShopSettings::defaultTelephonyCallFlow(),
            ['comms_attention_gate_enabled' => true],
        ),
    ]);
});

test('website lead broadcasts advisor interrupt', function (): void {
    Event::fake([CommsInterruptReceived::class]);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'How much for rear brakes on my Nissan Versa?',
        'contact_name' => 'Jeremiah Seress',
        'contact_phone' => '6365440991',
        'contact_email' => 'jbseress@gmail.com',
        'contact_preference' => LeadContactPreference::Text,
        'vehicle_year' => 2022,
        'vehicle_make' => 'Nissan',
        'vehicle_model' => 'Versa',
    ]);

    app(WebsiteLeadInterruptBroadcaster::class)->broadcast($lead);

    Event::assertDispatched(CommsInterruptReceived::class, function (CommsInterruptReceived $event): bool {
        return ($event->payload['kind'] ?? null) === 'website_lead'
            && ($event->payload['interrupt']['channel_label'] ?? null) === 'Website Lead'
            && ($event->payload['interrupt']['headline'] ?? null) === 'Jeremiah Seress';
    });

    $cached = Cache::get(WebsiteLeadInterruptBroadcaster::cacheKey());

    expect($cached)
        ->toBeArray()
        ->and($cached['kind'])->toBe('website_lead')
        ->and($cached['headline'])->toBe('Jeremiah Seress')
        ->and($cached['snippet'])->toContain('rear brakes');
});

test('comms interrupt api returns uncontacted website lead', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'AC is warm.',
        'contact_name' => 'Jordan Lee',
        'contact_phone' => '7195550143',
        'contact_preference' => LeadContactPreference::Text,
    ]);

    Cache::put(
        WebsiteLeadInterruptBroadcaster::cacheKey(),
        app(WebsiteLeadInterruptPresenter::class)->forLead($lead),
        now()->addHour(),
    );

    $this->actingAs($advisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('messages.0.kind', 'website_lead')
        ->assertJsonPath('messages.0.state', 'unread')
        ->assertJsonPath('messages.0.headline', 'Jordan Lee')
        ->assertJsonPath('messages.0.channel_label', 'Website Lead');
});

test('dismissed website lead interrupt is suppressed for advisor', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Noise when turning.',
        'contact_name' => 'Sam Rivera',
        'contact_phone' => '7195550144',
        'contact_preference' => LeadContactPreference::Call,
    ]);

    Cache::put(
        WebsiteLeadInterruptBroadcaster::cacheKey(),
        app(WebsiteLeadInterruptPresenter::class)->forLead($lead),
        now()->addHour(),
    );

    app(WebsiteLeadInterruptDismissal::class)->dismiss($advisor->id, $lead->id);

    $this->actingAs($advisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonMissing(['lead_id' => $lead->id]);
});

test('contacted website lead clears interrupt cache on interrupt poll', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Already handled.',
        'contact_name' => 'Alex',
        'contact_phone' => '7195550145',
        'contact_preference' => LeadContactPreference::Call,
        'first_contacted_at' => now(),
    ]);

    Cache::put(
        WebsiteLeadInterruptBroadcaster::cacheKey(),
        app(WebsiteLeadInterruptPresenter::class)->forLead($lead),
        now()->addHour(),
    );

    $this->actingAs($advisor)
        ->getJson(route('operations.comms.interrupts'))
        ->assertOk()
        ->assertJsonPath('messages', []);

    expect(Cache::get(WebsiteLeadInterruptBroadcaster::cacheKey()))->toBeNull();
});

test('spam website leads do not broadcast interrupt', function (): void {
    Event::fake([CommsInterruptReceived::class]);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Spam,
        'concern' => 'Spam message',
        'contact_name' => 'Bot Spam',
        'contact_phone' => '6365440991',
    ]);

    app(WebsiteLeadInterruptBroadcaster::class)->broadcast($lead);

    Event::assertNotDispatched(CommsInterruptReceived::class);
    expect(Cache::get(WebsiteLeadInterruptBroadcaster::cacheKey()))->toBeNull();
});

test('uncontacted website lead escalates to advisor phones after delay', function (): void {
    $transport = bindFakeOutboundSms();

    User::factory()->create([
        'phone' => '3035551212',
    ])->assignRole(ArkRole::Advisor->value);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'How much for rear brakes on my Nissan Versa?',
        'contact_name' => 'Jeremiah',
        'contact_phone' => '6365440991',
        'contact_preference' => LeadContactPreference::Text,
    ]);
    $lead->forceFill(['created_at' => now()->subMinutes(5)])->save();

    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195559999',
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'comms_escalation_enabled' => true,
            'comms_escalation_delay_minutes' => 3,
        ]),
    ]);

    $this->artisan('comms:escalate-unhandled')->assertSuccessful();

    expect($transport->sent)->toHaveCount(1)
        ->and($transport->sent[0]['to'])->toContain('3035551212')
        ->and($transport->sent[0]['body'])->toContain('Jeremiah')
        ->and($transport->sent[0]['body'])->toContain('website lead')
        ->and($transport->sent[0]['body'])->toContain(route('operations.leads.intake', $lead));
});

test('contacted website leads do not escalate', function (): void {
    $transport = bindFakeOutboundSms();

    User::factory()->create([
        'phone' => '3035551212',
    ])->assignRole(ArkRole::Advisor->value);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Already handled.',
        'contact_name' => 'Jordan',
        'contact_phone' => '6365440992',
        'contact_preference' => LeadContactPreference::Text,
        'first_contacted_at' => now()->subMinute(),
    ]);
    $lead->forceFill(['created_at' => now()->subMinutes(10)])->save();

    ShopSettings::current()->update([
        'telephony_inbound_number' => '+17195559999',
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'comms_escalation_enabled' => true,
            'comms_escalation_delay_minutes' => 1,
        ]),
    ]);

    $this->artisan('comms:escalate-unhandled')->assertSuccessful();

    expect($transport->sent)->toBe([]);
});
