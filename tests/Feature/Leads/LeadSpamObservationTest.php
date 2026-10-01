<?php

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadIngressContext;
use App\Ark\Operations\Leads\LeadIngressHygiene;
use App\Ark\Operations\Leads\LeadPressure;
use App\Ark\Operations\Leads\LeadRecorder;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('website lead captures ingress observation fields', function (): void {
    $ingress = new LeadIngressContext(
        ip: '203.0.113.10',
        userAgent: 'Mozilla/5.0 TestBrowser',
        referrer: 'https://google.com/search?q=auto+repair',
        formRenderedAt: now()->subSeconds(10),
        submittedAt: now(),
    );

    $lead = app(LeadRecorder::class)->recordWebsiteSubmission([
        'concern' => 'Brakes squeal when stopping.',
        'contact_phone' => '719-555-0142',
        'contact_name' => 'Alex Morgan',
        'source' => LeadSource::Website,
    ], ingress: $ingress);

    expect($lead->state)->toBe(LeadState::Received)
        ->and($lead->ingress_user_agent)->toContain('TestBrowser')
        ->and($lead->ingress_referrer)->toBe('https://google.com/search?q=auto+repair')
        ->and($lead->form_rendered_at)->not->toBeNull()
        ->and($lead->conversation_id)->not->toBeNull();
});

test('too fast submission auto flags spam without conversation', function (): void {
    $submittedAt = Carbon::now();
    $ingress = new LeadIngressContext(
        ip: '203.0.113.11',
        userAgent: 'Bot/1.0',
        referrer: null,
        formRenderedAt: $submittedAt->copy(),
        submittedAt: $submittedAt,
    );
    $hygiene = app(LeadIngressHygiene::class);
    $signals = $hygiene->signals($ingress);

    $lead = app(LeadRecorder::class)->recordWebsiteSubmission([
        'concern' => 'Casino bonus now!!!',
        'contact_phone' => '719-555-0001',
        'contact_name' => 'Bot Spam',
        'source' => LeadSource::Website,
    ], ingress: $ingress, forcedState: $hygiene->autoSpamState($signals), spamSignals: $signals);

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
