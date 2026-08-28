<?php

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('learn ark print selection page lists visible guides', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->get(route('operations.learn.print'))
        ->assertOk()
        ->assertSee('Print '.\App\Support\Branding\Branding::learnName())
        ->assertSee('Five steps to healthier margins')
        ->assertSee('Advisor basics')
        ->assertSee('Preview & print');
});

test('learn ark print preview renders only selected authorized guides', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->get(route('operations.learn.print', [
        'pick' => [
            'owner:daily-kpis',
            'advisor:repair-actions',
        ],
    ]))
        ->assertOk()
        ->assertSee('KPIs to review daily')
        ->assertSee('Repair actions')
        ->assertSee('Gross margin')
        ->assertDontSee('Five steps to healthier margins');
});

test('advisors cannot print owner guides', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->get(route('operations.learn.print', [
        'pick' => ['owner:daily-kpis'],
    ]))->assertNotFound();

    $this->get(route('operations.learn.print', [
        'pick' => ['advisor:repair-actions'],
    ]))
        ->assertOk()
        ->assertSee('Repair actions');
});

test('learn ark index links to print selection', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->get(route('operations.learn.index'))
        ->assertRedirect(route('operations.learn.show', ['role' => 'advisor', 'article' => 'getting-started']));

    $this->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'getting-started']))
        ->assertOk()
        ->assertSee('Print guides');
});
