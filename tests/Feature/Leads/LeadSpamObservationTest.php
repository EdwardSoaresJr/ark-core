<?php

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadPressure;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('website lead captures ingress observation fields', function (): void {
    $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0 TestBrowser',
        'Referer' => 'https://google.com/search?q=auto+repair',
    ])->post(route('public.leads.store'), [
        'concern' => 'Brakes squeal when stopping.',
        'phone' => '719-555-0142',
        'first_name' => 'Alex',
        'last_name' => 'Morgan',
        'form_rendered_at' => now()->subSeconds(10)->timestamp,
    ])->assertRedirect(route('public.leads.thanks'));

    $lead = Lead::query()->sole();

    expect($lead->state)->toBe(LeadState::Received)
        ->and($lead->ingress_user_agent)->toContain('TestBrowser')
        ->and($lead->ingress_referrer)->toBe('https://google.com/search?q=auto+repair')
        ->and($lead->form_rendered_at)->not->toBeNull()
        ->and($lead->conversation_id)->not->toBeNull();
});

test('too fast submission auto flags spam without conversation', function (): void {
    $this->post(route('public.leads.store'), [
        'concern' => 'Casino bonus now!!!',
        'phone' => '719-555-0001',
        'first_name' => 'Bot',
        'last_name' => 'Spam',
        'form_rendered_at' => now()->timestamp,
    ])->assertRedirect(route('public.leads.thanks'));

    $lead = Lead::query()->sole();

    expect($lead->state)->toBe(LeadState::Spam)
        ->and($lead->spam_signals)->toContain('too_fast')
        ->and($lead->conversation_id)->toBeNull();
});

test('spam leads are excluded from lead pressure', function (): void {
    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Spam,
        'concern' => 'SEO services',
        'contact_phone' => '7195550999',
        'spam_signals' => ['too_fast'],
    ]);

    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'AC not cold',
        'contact_phone' => '7195550101',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $pressure = app(LeadPressure::class)->resolve($advisor);

    expect($pressure['open_count'])->toBe(1)
        ->and($pressure['new_count'])->toBe(1);
});

test('advisor can mark lead spam manually', function (): void {
    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'Hello dear',
        'contact_phone' => '7195550104',
    ]);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->patch(route('operations.leads.state', $lead), ['state' => LeadState::Spam->value])
        ->assertRedirect();

    expect($lead->fresh()->state)->toBe(LeadState::Spam);
});

test('spam observation section shows ingress metadata on website performance', function (): void {
    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Spam,
        'concern' => 'Crypto investment',
        'contact_phone' => '7195550888',
        'ingress_ip' => '203.0.113.50',
        'ingress_referrer' => 'https://spam.example',
        'ingress_user_agent' => 'python-requests/2.31',
        'spam_signals' => ['too_fast'],
    ]);

    $owner = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($owner)
        ->get(route('website.performance'))
        ->assertOk()
        ->assertSee('Spam observation')
        ->assertSee('203.0.113.50')
        ->assertSee('python-requests')
        ->assertSee('Crypto investment');
});
