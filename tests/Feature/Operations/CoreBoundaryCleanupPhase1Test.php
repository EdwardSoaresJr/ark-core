<?php

use App\Ark\Dragon\Agent\DragonAgentMemory;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use App\Support\Branding\Branding;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
});

test('core rail keeps arkademy and stations without a platform heading', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('ops-rail-section__label">Platform</p>', false)
        ->assertSee('ops-rail-section__label">System</p>', false)
        ->assertSee('Stations &amp; Phones', false)
        ->assertSee(route('operations.shop.communications'), false)
        ->assertSee(Branding::learnName(), false)
        ->assertSee('Communications', false);
});

test('shop settings no longer expose dragon memory as an operational domain', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    DragonAgentMemory::query()->create([
        'fact_key' => 'taught:'.Str::uuid(),
        'fact_value' => 'Keep superseded memories out of shop settings.',
        'scope_type' => 'company',
        'category' => 'standard',
        'taught_by' => 'Edward',
        'provenance' => 'test',
    ]);

    $this->actingAs($admin)
        ->get(route('operations.settings.shop.edit'))
        ->assertOk()
        ->assertDontSee('Dragon Memory', false)
        ->assertDontSee('Hosted Dragon', false)
        ->assertDontSee('Keep superseded memories out of shop settings.', false)
        ->assertSee('Square Payments', false);
});

test('hosted square settings hide provider secrets and keep capture surfaces', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    enableHostedPlatformPayments();

    $this->actingAs($admin)
        ->get(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertOk()
        ->assertSee('managed by ARK Platform', false)
        ->assertSee('Counter terminal', false)
        ->assertSee('Counter keyed entry', false)
        ->assertSee('Customer portal pay', false)
        ->assertSee('Emailed invoice pay link', false)
        ->assertDontSee('name="square_access_token"', false)
        ->assertDontSee('name="square_application_id"', false)
        ->assertDontSee('Generate pairing code', false)
        ->assertDontSee('Save Square API credentials here', false)
        ->assertDontSee('Webhook URL', false);
});
