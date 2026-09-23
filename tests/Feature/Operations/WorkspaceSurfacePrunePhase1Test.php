<?php

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
});

test('legacy comms workboard redirects to attention workspace', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.communications.workboard'))
        ->assertRedirect(CommunicationsNeedsYou::url());
});

test('inbox and history remain first class comms workspace sections', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url())
        ->assertOk()
        ->assertSee('Needs attention', false);

    $this->actingAs($advisor)
        ->get(route('operations.communications.history'))
        ->assertOk()
        ->assertSee('History', false);
});

test('inbox preserves thread selection query', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['conversation' => 42]))
        ->assertOk()
        ->assertSee('conversation=42', false);
});

test('conversation reply page redirects to attention thread composer', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $conversation = Conversation::query()->create([
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => '+17195551212',
        'status' => \App\Ark\Operations\Conversations\ConversationStatus::Open,
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.conversations.reply', $conversation).'?compose=text')
        ->assertRedirect(CommunicationsNeedsYou::url([
            'conversation' => $conversation->id,
            'compose' => 'text',
        ]).'#conversation-composer');
});

test('staff with admin permissions see full operations left rail destinations', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $advisor->givePermissionTo('settings.manage', 'customers.manage', 'repair_orders.view', 'financial.view');

    $response = $this->actingAs($advisor)->get(route('operations.index'));

    $response->assertOk()
        ->assertSee('ops-rail-nav', false)
        ->assertSee(route('operations.index'), false)
        ->assertSee(CommunicationsNeedsYou::url(), false)
        ->assertSee(route('operations.intake.create'), false)
        ->assertDontSee('>Intake</span>', false)
        ->assertDontSee('ops-rail-section__label">Home</p>', false)
        ->assertSee('ops-rail-section__label">Records</p>', false)
        ->assertDontSee('ops-rail-section__label">Customers</p>', false)
        ->assertDontSee(route('operations.leads.index'), false)
        ->assertSee(route('operations.repair-orders.index'), false)
        ->assertSee(route('operations.customers.search'), false)
        ->assertSee(route('operations.vehicles.search'), false)
        ->assertSee(route('operations.shop.communications'), false)
        ->assertSee('Stations &amp; Phones', false)
        ->assertDontSee('ops-rail-section__label">Platform</p>', false)
        ->assertFalse(\Illuminate\Support\Facades\Route::has('website.manage'))
        ->assertFalse(\Illuminate\Support\Facades\Route::has('growth.dashboard'))
        ->assertDontSee('>Website</span>', false)
        ->assertDontSee('>Growth</span>', false);

    $this->actingAs($advisor)
        ->get(route('operations.settings.shop.edit'))
        ->assertOk()
        ->assertDontSee('>Website</a>', false)
        ->assertDontSee('>Growth</a>', false);
});

test('communications section nav lists inbox calls and history in that order', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $response = $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url())
        ->assertOk()
        ->assertSee('Inbox', false)
        ->assertSee('Needs attention', false)
        ->assertSee('Waiting', false)
        ->assertSee('History', false)
        ->assertSee('Calls &amp; VM', false);

    // Internal channels have no workspace tab - notes live in each thread.
    $nav = str($response->getContent())->between('Communications sections', '</nav>');
    expect($nav->contains('Internal'))->toBeFalse()
        ->and($nav->value())->toMatch('/Inbox.*Calls &amp; VM.*History/s');

    $this->actingAs($advisor)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.communications.calls'))
        ->assertOk()
        ->assertSee('Calls &amp; Voicemail', false);
});
