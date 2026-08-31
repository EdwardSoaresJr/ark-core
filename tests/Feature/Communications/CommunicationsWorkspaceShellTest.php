<?php

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
use App\Ark\Operations\Communications\InternalChannel;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\InternalChannelSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(InternalChannelSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
});

test('communications index redirects to attention workspace', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.communications.index'))
        ->assertRedirect(CommunicationsNeedsYou::url());
});

test('advisor can open attention and internal workspace shells', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url())
        ->assertOk()
        ->assertSee('ops-comms-workspace', false)
        ->assertSee('Needs attention', false)
        ->assertSee('Inbox', false);

    $this->actingAs($advisor)
        ->get(route('operations.communications.internal'))
        ->assertOk()
        ->assertSee('General', false)
        ->assertSee('Management', false);
});

test('internal channel workspace shows read only thread shell', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $channel = InternalChannel::query()->where('slug', 'general')->firstOrFail();

    $this->actingAs($advisor)
        ->get(route('operations.communications.internal.channel', $channel))
        ->assertOk()
        ->assertSee('General', false)
        ->assertSee('Internal only', false);
});

test('technician can open internal workspace but not attention inbox', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $this->actingAs($technician)
        ->get(route('operations.communications.inbox'))
        ->assertForbidden();

    $this->actingAs($technician)
        ->get(CommunicationsNeedsYou::url())
        ->assertForbidden();

    $this->actingAs($technician)
        ->get(route('operations.communications.internal'))
        ->assertOk()
        ->assertSee('Internal channels', false);

    expect($technician->can(ArkCapability::CommunicationsInternalView->value))->toBeTrue()
        ->and($technician->can(ArkCapability::OperationsAccess->value))->toBeFalse();
});

test('legacy workboard redirects to attention workspace', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.communications.workboard'))
        ->assertRedirect(CommunicationsNeedsYou::url());
});
