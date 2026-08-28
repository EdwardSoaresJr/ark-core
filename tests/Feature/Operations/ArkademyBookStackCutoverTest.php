<?php

use App\Ark\Operations\Learn\ArkademyUrls;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\ArkademyContentRegistry;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('embedded learn routes redirect to bookstack when cutover is enabled', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    config([
        'bookstack.cutover' => true,
        'bookstack.base_url' => 'https://learn.test',
        'bookstack.shelf_slug' => 'shop-in-a-box',
    ]);

    ArkademyContentRegistry::query()->create([
        'source_type' => 'page',
        'bookstack_id' => 99,
        'bookstack_url' => 'https://learn.test/books/advisor-operations/page/advisor-basics',
        'visibility' => 'base',
        'legacy_key' => 'advisor:getting-started',
        'title' => 'Advisor basics',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'getting-started']))
        ->assertRedirect('https://learn.test/books/advisor-operations/page/advisor-basics');

    $this->actingAs($advisor)
        ->get(route('operations.learn.index'))
        ->assertRedirect('https://learn.test/books/advisor-operations/page/advisor-basics');
});

test('staff nav uses bookstack home when cutover is enabled', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    config([
        'bookstack.cutover' => true,
        'bookstack.base_url' => 'https://learn.test',
        'bookstack.shelf_slug' => 'shop-in-a-box',
    ]);

    completeRequiredLearnFor(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.index'))
        ->assertSee('https://learn.test/shelves/shop-in-a-box', false);
});

test('training gate is inactive during bookstack cutover', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config(['bookstack.cutover' => true]);
    ShopSettings::current()->update(['learn_training_gate_enabled' => true]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk();
});
