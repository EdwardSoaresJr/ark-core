<?php

use App\Ark\Operations\Learn\LearnArkPreviewHtml;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;

test('learn preview returns article json for authorized staff', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $this->actingAs($admin)
        ->getJson(route('operations.learn.preview', [
            'role' => 'admin',
            'article' => 'comms-health-check',
        ]))
        ->assertOk()
        ->assertJsonPath('role', 'admin')
        ->assertJsonPath('slug', 'comms-health-check')
        ->assertJsonPath('title', 'Communications health check')
        ->assertJsonStructure(['summary', 'section_label', 'html', 'arkademy_url']);
});

test('learn preview denies articles outside staff visibility', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);

    $this->actingAs($technician)
        ->getJson(route('operations.learn.preview', [
            'role' => 'admin',
            'article' => 'comms-health-check',
        ]))
        ->assertNotFound();
});

test('learn preview html rewrites internal guide links for modal navigation', function () {
    $html = LearnArkPreviewHtml::render('admin', [
        'slug' => 'getting-started',
        'title' => 'Admin overview',
        'summary' => 'Shop settings and staff.',
        'view' => 'operations.learn.admin.getting-started',
    ]);

    expect($html)
        ->toContain('data-arkademy-guide="admin:telephony-sip-setup"')
        ->not->toContain(route('operations.learn.show', ['role' => 'admin', 'article' => 'telephony-sip-setup']));
});

test('settings surfaces render arkademy guide modal triggers', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Admin))
        ->get(route('operations.settings.shop.edit', ['section' => 'general']))
        ->assertOk()
        ->assertSee('ops-learn-guide-modal', false);
});
