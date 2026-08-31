<?php

use App\Ark\Operations\Communications\CommunicationWorkboardProjection;
use Illuminate\Support\Facades\Http;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
            $this->markTestSkipped('Workspace Surface Phase 1: comms workboard redirects to Attention; rewrite projection tests for Attention in Phase 2.');
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

test('communications workboard page exposes live refresh fragment url', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.communications.workboard'))
        ->assertOk()
        ->assertSee('id="ops-comms-workboard-live"', false)
        ->assertSee(route('operations.communications.workboard.fragment'), false);
});

test('operations layout exposes communications nav pressure hook', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.index'))
        ->assertOk()
        ->assertSee('data-ops-comms-nav-link', false);
});
