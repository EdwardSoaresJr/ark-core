<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    ShopSettings::current()->persistTrusted([
        'learn_training_gate_enabled' => false,
    ]);
    Carbon::setTestNow(Carbon::parse('2026-06-06 04:00:00', config('app.timezone')));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('financial users can open the reports catalog', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->get(route('operations.reports.index'))
        ->assertOk()
        ->assertSee('Pick a report', false)
        ->assertSee('End of Day', false)
        ->assertSee('Sales &amp; Payments', false)
        ->assertSee('Margin Health', false)
        ->assertSee('Owner P&amp;L', false)
        ->assertSee('Operations Pulse', false)
        ->assertSee('Production', false);
});

test('advisors route end of day card to the standalone eod report', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $shopDay = OperationalReportDateScope::shopDateString(OperationalReportDateScope::shopNow());

    $this->get(route('operations.reports.index'))
        ->assertOk()
        ->assertSee(route('operations.reports.end-of-day', ['date' => $shopDay], false), false);

    $this->get(route('operations.reports.end-of-day'))
        ->assertOk()
        ->assertSee('End of Day Report', false)
        ->assertSee('RO Summary', false)
        ->assertSee('All reports', false);
});

test('admins route end of day card to day review', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $shopDay = OperationalReportDateScope::shopDateString(OperationalReportDateScope::shopNow());

    $this->get(route('operations.reports.index'))
        ->assertOk()
        ->assertSee(route('operations.owner.day-review', ['date' => $shopDay], false), false);
});

test('operational report links back to the reports catalog', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->get(route('operations.reports.operational', ['tab' => 'financial']))
        ->assertOk()
        ->assertSee('All reports', false);
});

test('technicians cannot access the reports catalog', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Technician->value));

    $this->get(route('operations.reports.index'))
        ->assertForbidden();
});
