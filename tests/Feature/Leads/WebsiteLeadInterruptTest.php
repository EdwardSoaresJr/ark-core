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
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('public_lead.send_confirmation', false);
    config()->set('public_lead.phone_verification_required', false);

    $this->seed(ArkAuthorizationSeeder::class);

    ShopSettings::current()->update([
        'learn_training_gate_enabled' => false,
        'telephony_call_flow' => array_merge(
            ShopSettings::defaultTelephonyCallFlow(),
            ['comms_attention_gate_enabled' => true],
        ),
    ]);
});

test('public website lead broadcasts advisor interrupt', function (): void {
    Event::fake([CommsInterruptReceived::class]);

    $this->post(route('public.leads.store'), [
        'concern' => 'How much for rear brakes on my Nissan Versa?',
        'phone' => '636-544-0991',
        'first_name' => 'Jeremiah',
        'last_name' => 'Seress',
        'email' => 'jbseress@gmail.com',
        'contact_preference' => LeadContactPreference::Text->value,
        'vehicle_year' => 2022,
        'vehicle_make' => 'Nissan',
        'vehicle_model' => 'Versa',
        'source' => LeadSource::Website->value,
        'form_rendered_at' => now()->subSeconds(5)->timestamp,
    ])->assertRedirect(route('public.leads.thanks'));

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

test('website lead interrupt is hidden after advisor dismissal', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Brakes squeal.',
        'contact_name' => 'Alex Morgan',
        'contact_phone' => '7195550142',
        'contact_preference' => LeadContactPreference::Text,
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
        ->assertJsonPath('messages', []);
});

test('website lead interrupt clears after first contact', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Check engine light.',
        'contact_name' => 'Sam Rivera',
        'contact_phone' => '7195550199',
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

    $this->post(route('public.leads.store'), [
        'concern' => 'Spam message',
        'phone' => '636-544-0991',
        'first_name' => 'Bot',
        'last_name' => 'Spam',
        'source' => LeadSource::Website->value,
        'form_rendered_at' => now()->timestamp,
    ])->assertRedirect(route('public.leads.thanks'));

    Event::assertNotDispatched(CommsInterruptReceived::class);
    expect(Cache::get(WebsiteLeadInterruptBroadcaster::cacheKey()))->toBeNull();
});

test('uncontacted website lead escalates to advisor phones after delay', function (): void {
    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'SMleadesc001', 'status' => 'queued'], 201),
    ]);

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
        'twilio_account_sid' => 'ACtestaccount',
        'twilio_auth_token' => 'test-token',
        'telephony_inbound_number' => '+17195559999',
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'comms_escalation_enabled' => true,
            'comms_escalation_delay_minutes' => 3,
        ]),
    ]);

    $this->artisan('comms:escalate-unhandled')->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(function ($request) use ($lead): bool {
        $body = urldecode((string) $request->body());

        return str_contains($body, 'Jeremiah')
            && str_contains($body, 'website lead')
            && str_contains($body, route('operations.leads.intake', $lead));
    });
});

test('contacted website leads do not escalate', function (): void {
    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'SMleadesc002', 'status' => 'queued'], 201),
    ]);

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
        'twilio_account_sid' => 'ACtestaccount',
        'twilio_auth_token' => 'test-token',
        'telephony_inbound_number' => '+17195559999',
        'telephony_call_flow' => array_merge(ShopSettings::defaultTelephonyCallFlow(), [
            'comms_escalation_enabled' => true,
            'comms_escalation_delay_minutes' => 1,
        ]),
    ]);

    $this->artisan('comms:escalate-unhandled')->assertSuccessful();

    Http::assertNothingSent();
});
