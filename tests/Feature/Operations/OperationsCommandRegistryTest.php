<?php

use App\Ark\Operations\Commands\OperationsCommand;
use App\Ark\Operations\Commands\OperationsCommandRegistry;
use App\Ark\Operations\Commands\RegisterCoreOperationsCommands;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('substring filter matches title and keywords', function () {
    $registry = new OperationsCommandRegistry;
    $registry->register(new OperationsCommand(
        id: 'create.repair-order',
        title: 'New Repair Order',
        group: 'Create',
        keywords: ['ro', 'repair', 'intake'],
        url: '/app/intake/create',
    ));
    $registry->register(new OperationsCommand(
        id: 'nav.customers',
        title: 'Customers',
        group: 'Navigate',
        keywords: ['customer'],
        url: '/app/customers',
    ));

    $matched = $registry->filter('repair', null);

    expect($matched)->toHaveCount(1)
        ->and($matched[0]->id)->toBe('create.repair-order');
});

test('permission hides unavailable commands', function () {
    $registry = new OperationsCommandRegistry;
    $registry->register(new OperationsCommand(
        id: 'nav.settings',
        title: 'Settings',
        group: 'Navigate',
        permission: ArkCapability::SettingsManage->value,
        url: '/app/settings',
    ));
    $registry->register(new OperationsCommand(
        id: 'nav.workboard',
        title: "Today's Workboard",
        group: 'Navigate',
        permission: ArkCapability::OperationsAccess->value,
        url: '/app',
    ));

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $ids = array_map(fn (OperationsCommand $command) => $command->id, $registry->forUser($advisor));

    expect($ids)->toContain('nav.workboard')
        ->and($ids)->not->toContain('nav.settings');
});

test('core commands register navigate create search and operations groups', function () {
    $registry = app(OperationsCommandRegistry::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $commands = $registry->forUser($advisor);
    $groups = collect($commands)->pluck('group')->unique()->values()->all();
    $titles = collect($commands)->pluck('title')->all();

    expect($groups)->toContain('Navigate')
        ->and($groups)->toContain('Create')
        ->and($groups)->toContain('Search')
        ->and($groups)->toContain('Operations')
        ->and($titles)->toContain("Today's Workboard")
        ->and($titles)->toContain('New Repair Order')
        ->and($titles)->toContain('Print Key Tag')
        ->and($titles)->toContain('Search by VIN');
});

test('print commands are disabled without a repair order context', function () {
    $registry = new OperationsCommandRegistry;
    app(RegisterCoreOperationsCommands::class)($registry);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $print = collect($registry->forUser($advisor))->firstWhere('id', 'ops.print-key-tag');

    expect($print)->not->toBeNull()
        ->and($print->url)->toBeNull()
        ->and($print->disabledReason)->toBe('Open a repair order first.');
});
