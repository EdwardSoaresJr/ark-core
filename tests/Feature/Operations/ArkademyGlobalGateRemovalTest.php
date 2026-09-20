<?php

use App\Ark\Operations\Learn\LearnArkTrainingGate;
use App\Ark\Operations\Learn\LearnCompletion;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

/**
 * Shop-wide Learn/ARKademy workboard gate is retired.
 * Learning remains available; progress data must survive; no admin escalation.
 */
beforeEach(function () {
    config(['bookstack.cutover' => false]);
    // Even if the legacy setting is on, the global gate must not enforce.
    ShopSettings::current()->update(['learn_training_gate_enabled' => true]);
});

test('authorized advisor reaches workboard without completing learn guides', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    expect(LearnArkTrainingGate::isActiveFor($advisor))->toBeFalse();

    $this->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('Complete required');
});

test('authorized advisor can open learn index and a guide', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    // Index redirects into the first track article; follow and assert Learn UI.
    $this->get(route('operations.learn.index'))
        ->assertRedirect(route('operations.learn.show', [
            'role' => 'advisor',
            'article' => 'getting-started',
        ]));

    $this->get(route('operations.learn.show', [
        'role' => 'advisor',
        'article' => 'getting-started',
    ]))->assertOk()
        ->assertSee('Advisor basics', false)
        ->assertSee('Staff training', false)
        ->assertDontSee('Pause gate for all staff', false);
});

test('existing learn completion rows remain readable after gate removal', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    LearnCompletion::query()->create([
        'user_id' => $advisor->id,
        'article_key' => 'advisor:getting-started',
        'completed_at' => now(),
        'active_seconds' => 120,
        'catalog_version' => 1,
        'article_version' => 1,
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.learn.show', [
            'role' => 'advisor',
            'article' => 'getting-started',
        ]))
        ->assertOk();

    expect(LearnCompletion::query()
        ->where('user_id', $advisor->id)
        ->where('article_key', 'advisor:getting-started')
        ->exists())->toBeTrue();
});

test('training gate endpoint cannot re-enable shop-wide workboard blocking', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $owner = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($owner)
        ->post(route('operations.learn.training-gate'), ['enabled' => true])
        ->assertRedirect()
        ->assertSessionHas('learn_gate_control');

    expect(ShopSettings::current()->fresh()->learn_training_gate_enabled)->toBeFalse()
        ->and(LearnArkTrainingGate::isShopEnabled())->toBeFalse();

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk();
});

test('non-owner admin cannot call retired training gate endpoint', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($admin);

    $this->post(route('operations.learn.training-gate'), ['enabled' => false])
        ->assertForbidden();
});

test('guest cannot open learn without authentication', function () {
    $this->get(route('operations.learn.index'))
        ->assertRedirect();
});
