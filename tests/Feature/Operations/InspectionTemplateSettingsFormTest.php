<?php

use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\InspectionTemplateCatalog;
use App\Ark\Operations\Inspections\InspectionTemplateItem;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('inspection settings show each measurement slot name and type', function () {
    DefaultInspectionTemplateCatalog::seedIfMissing();

    $html = view('operations.settings.partials.inspection-template-settings', [
        'inspectionTemplates' => InspectionTemplateCatalog::settingsFormData(),
    ])->render();

    expect($html)
        ->toContain('Inner pad')
        ->toContain('>Number</option>')
        ->toContain('>Condition</option>')
        ->toContain('Lining / shoe')
        ->not->toContain('placeholder="Measurement name"');
});

test('saving inspection settings keeps slot identity and updates name and type', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    DefaultInspectionTemplateCatalog::seedIfMissing();

    $item = InspectionTemplateItem::query()->where('label', 'LF Brake pads')->firstOrFail();
    $before = $item->measurement_slots;
    $category = $item->category;

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->patch(route('operations.settings.shop.inspection-templates.update'), [
            'templates' => [
                $category->inspection_template_id => [
                    'enabled' => '1',
                    'categories' => [
                        $category->id => [
                            'items' => [
                                $item->id => [
                                    'label' => 'LF Brake pads',
                                    'enabled' => '1',
                                    'slots' => [
                                        ['name' => 'Inner pad thickness', 'type' => 'number', 'unit' => 'mm'],
                                        ['name' => 'Outer pad', 'type' => 'text', 'unit' => 'mm'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'workflow',
            'workflow-tab' => 'inspections',
        ]))
        ->assertSessionHas('status', 'Inspection checklists saved.');

    $slots = $item->fresh()->measurement_slots;

    expect($slots[0]['key'])->toBe($before[0]['key'])
        ->and($slots[0]['required'])->toBeTrue()
        ->and($slots[0]['name'])->toBe('Inner pad thickness')
        ->and($slots[0]['type'])->toBe('number')
        ->and($slots[1]['key'])->toBe($before[1]['key'])
        ->and($slots[1]['name'])->toBe('Outer pad')
        ->and($slots[1]['type'])->toBe('text')
        ->and($slots[1]['unit'])->toBe('mm');
});
