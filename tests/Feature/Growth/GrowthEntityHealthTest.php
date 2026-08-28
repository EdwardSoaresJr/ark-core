<?php

use App\Ark\Growth\EntityHealth\EntityHealthEngine;
use App\Ark\Growth\Settings\AudienceSurfaceVerifications;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

it('summarizes canonical identity and notebook without scores', function (): void {
    ShopSettings::current()->update([
        'shop_name' => 'Demo Auto Repair',
        'address_line_1' => '100 Main Street',
        'city' => 'Colorado Springs',
        'state' => 'CO',
        'postal_code' => '80909',
        'phone' => '7194136227',
        'website' => 'https://demo-auto.test',
    ]);

    $summary = app(EntityHealthEngine::class)->summarize();

    expect($summary['canonical'])->not->toBeEmpty()
        ->and(collect($summary['canonical'])->firstWhere('key', 'business_name')['value'])->toBe('Demo Auto Repair')
        ->and($summary['notebook']['summary'])->toContain('require attention')
        ->and($summary['notebook']['last_reviewed_at'])->not->toBe('')
        ->and($summary)->not->toHaveKey('score');
});

it('formats notebook summary for zero, one, and multiple findings', function (): void {
    $engine = app(EntityHealthEngine::class);
    $method = new ReflectionMethod($engine, 'notebook');
    $method->setAccessible(true);

    $zero = $method->invoke($engine, []);
    expect($zero['summary'])->toBe('Public identity remains consistent.')
        ->and($zero['bullets'])->toBe([])
        ->and($zero['last_reviewed_at'])->not->toBe('');

    $one = $method->invoke($engine, [['notebook_line' => 'JSON-LD address omits suite designation.']]);
    expect($one['summary'])->toBe('1 public identity inconsistency requires attention.')
        ->and($one['bullets'])->toBe(['JSON-LD address omits suite designation.']);

    $two = $method->invoke($engine, [
        ['notebook_line' => 'JSON-LD address omits suite designation.'],
        ['notebook_line' => 'JSON-LD omits business hours.'],
    ]);
    expect($two['summary'])->toBe('2 public identity inconsistencies require attention.')
        ->and($two['bullets'])->toHaveCount(2);
});

it('projects suite into public street address and json-ld', function (): void {
    ShopSettings::current()->update([
        'shop_name' => 'Demo Auto Repair',
        'address_line_1' => '100 Main Street',
        'address_line_2' => 'Unit B',
        'city' => 'Colorado Springs',
        'state' => 'CO',
        'postal_code' => '80909',
        'phone' => '7194136227',
    ]);

    $summary = app(EntityHealthEngine::class)->summarize();
    $suite = collect($summary['identity_observations'])->firstWhere('id', 'suite_schema_drift');

    expect($suite)->toBeNull()
        ->and($summary['notebook']['bullets'])->not->toContain('JSON-LD address omits suite designation.')
        ->and(ShopSettings::current()->googleMatchedStreetAddress())->toBe('100 Main Street B');
});

it('surfaces actionable identity observations with canonical and projection labels', function (): void {
    ShopSettings::current()->update([
        'shop_name' => 'Demo Auto Repair',
        'address_line_1' => '100 Main Street',
        'address_line_2' => 'Unit B',
        'city' => 'Colorado Springs',
        'state' => 'CO',
        'postal_code' => '80909',
        'phone' => '7194136227',
    ]);

    $findings = app(EntityHealthEngine::class)->summarize()['identity_observations'];

    expect(collect($findings)->firstWhere('id', 'suite_schema_drift'))->toBeNull();
});

it('exposes entity health page and marks audiences verified by date', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('growth.entity-health'))
        ->assertOk()
        ->assertSee('Entity Health', false)
        ->assertSee('Audiences', false)
        ->assertSee('Notebook', false)
        ->assertSee('Identity Observations', false)
        ->assertSee('Google Business Profile', false)
        ->assertDontSee('Entity Score', false);

    $this->actingAs($admin)
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->patch(route('growth.entity-health.verify', 'google_business_profile'))
        ->assertRedirect(route('growth.entity-health'));

    $surface = AudienceSurfaceVerifications::surfacesForDisplay()['google_business_profile'];

    expect($surface['last_verified_at'])->toBe(now()->toDateString())
        ->and($surface['is_stale'])->toBeFalse();
});
