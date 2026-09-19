<?php

use App\Ark\Operations\Communications\CommunicationsQuickReplyTemplates;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('canned responses start from seeded defaults and stay deleted', function () {
    $settings = ShopSettings::current();

    expect($settings->quick_reply_templates)->toHaveCount(4)
        ->and($settings->quick_reply_templates[0]['label'])->toBe('Estimate received');

    $remaining = collect(CommunicationsQuickReplyTemplates::defaults())
        ->reject(fn (array $row): bool => $row['label'] === 'Financing')
        ->values()
        ->all();

    $settings->update(['quick_reply_templates' => $remaining]);
    ShopSettings::forgetCurrent();

    $labels = collect(CommunicationsQuickReplyTemplates::all())->pluck('label');

    expect($labels)->toContain('Scheduling')
        ->and($labels)->not->toContain('Financing');
});

test('communications settings can add and delete canned responses', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.settings.shop.edit', [
            'section' => 'communications',
            'communications-tab' => 'general',
        ]))
        ->assertOk()
        ->assertSee('Canned responses')
        ->assertSee('Chip color')
        ->assertSee('Send Address')
        ->assertSee('Menu color')
        ->assertSee('Estimate received')
        ->assertSee('Add response');

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'general',
            'quick_reply_templates_present' => '1',
            'quick_reply_templates' => [
                [
                    'label' => 'Running behind',
                    'body' => 'We are running behind. We will text when the car is ready.',
                ],
                [
                    'label' => 'Loaner',
                    'body' => 'A loaner is available if you need one today.',
                    'color' => 'blue',
                ],
            ],
        ])
        ->assertRedirect();

    ShopSettings::forgetCurrent();

    $templates = CommunicationsQuickReplyTemplates::all();

    expect($templates)->toHaveCount(2)
        ->and($templates[0]['label'])->toBe('Running behind')
        ->and($templates[0]['color'])->toBe('neutral')
        ->and($templates[1]['label'])->toBe('Loaner')
        ->and($templates[1]['color'])->toBe('blue');

    $html = view('operations.communications.workspace.partials.quick-replies')->render();

    expect($html)->toContain('Loaner')
        ->and($html)->toContain('background:#f0f9ff')
        ->and($html)->not->toContain('Estimate received');
});

test('saving call hours does not change canned responses', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    ShopSettings::current()->update([
        'quick_reply_templates' => [
            ['key' => 'loaner', 'label' => 'Loaner', 'body' => 'A loaner is available.'],
        ],
    ]);
    ShopSettings::forgetCurrent();

    $this->actingAs($admin)
        ->patch(route('operations.settings.shop.telephony.update'), [
            'communications_tab' => 'hours',
            'telephony_call_flow' => [
                'timezone' => 'America/Denver',
            ],
        ])
        ->assertRedirect();

    ShopSettings::forgetCurrent();

    expect(CommunicationsQuickReplyTemplates::all())->toHaveCount(1)
        ->and(CommunicationsQuickReplyTemplates::all()[0]['label'])->toBe('Loaner');
});
