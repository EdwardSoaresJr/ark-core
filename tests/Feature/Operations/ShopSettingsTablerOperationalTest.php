<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

function shopSettingsXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

function shopSettingsHasClass(string $class): string
{
    return 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
}

test('workflow defaults and label printing are tabler cards outside the legacy island', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $xpath = shopSettingsXpath($this->get(route('operations.settings.shop.edit', ['section' => 'workflow']))->assertOk()->getContent());

    foreach (['workflow', 'printing'] as $section) {
        $card = $xpath->query('//section[@data-settings-section="'.$section.'"]['.shopSettingsHasClass('card').']')->item(0);

        expect($card)->toBeInstanceOf(DOMElement::class)
            ->and($xpath->query('ancestor::*['.shopSettingsHasClass('ark-legacy').']', $card)->length)->toBe(0)
            ->and($xpath->query('.//*['.shopSettingsHasClass('ark-legacy').']', $card)->length)->toBe(0)
            ->and($xpath->query('//*['.shopSettingsHasClass('ark-legacy').']//*[@x-show="active === \''.$section.'\'"]')->length)->toBe(0);
    }

    expect($xpath->query('//section[@data-settings-section="workflow"]//button[@role="tab"]')->length)->toBe(4)
        ->and($xpath->query('//section[@data-settings-section="printing"]//button[@role="tab"]')->length)->toBe(2);
});

test('migrated settings forms keep their routes and field names', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $xpath = shopSettingsXpath($this->get(route('operations.settings.shop.edit', ['section' => 'workflow']))->assertOk()->getContent());
    $workflow = '//section[@data-settings-section="workflow"]';
    $printing = '//section[@data-settings-section="printing"]//form[@action="'.route('operations.settings.shop.printing.update').'"]';

    foreach ([
        route('operations.settings.shop.workflow.update') => ['default_visit_mode', 'default_recommendation_posture', 'default_recommendation_intent', 'default_notes_visible_to_advisor', 'default_notes_visible_to_technician', 'default_notes_visible_to_customer'],
        route('operations.settings.shop.job-board-lanes.update') => ['lanes[estimates][name]', 'lanes[estimates][color]', 'lanes[estimates][sort_order]', 'lanes[estimates][active]', 'create[name]', 'create[color]'],
        route('operations.settings.shop.status-catalog.update') => ['create_transition[from_slug]', 'create_transition[to_slug]', 'create_transition[roles][]', 'create[name]', 'create[slug]', 'create[advisor_lane_key]', 'create[mileage_requirement]', 'statuses[draft][name]', 'statuses[draft][color]', 'statuses[draft][mileage_requirement]'],
        route('operations.settings.shop.work-templates.store') => ['title', 'description', 'internal_note'],
    ] as $action => $fields) {
        foreach ($fields as $field) {
            expect($xpath->query($workflow.'//form[@action="'.$action.'"]//*[@name="'.$field.'"]')->length)
                ->toBeGreaterThan(0, "{$field} is missing from {$action}");
        }
    }

    foreach (['qz_printing_enabled', 'qz_printing_key_tag_printer', 'qz_printing_oil_sticker_printer', 'qz_key_tag_label_width_mm', 'qz_key_tag_label_height_mm', 'qz_key_tag_orientation', 'qz_key_tag_media_type', 'qz_key_tag_vin_display', 'key_tag_mileage_requirement', 'qz_raster_dpi', 'oil_change_interval_miles', 'oil_change_sticker_next_due_months'] as $field) {
        expect($xpath->query($printing.'//*[@name="'.$field.'"]')->length)->toBeGreaterThan(0, "{$field} is missing from the printing form");
    }

    expect($xpath->query($workflow.'//*['.shopSettingsHasClass('form-selectgroup').']//input[@id="default-note-audience-technician"]')->length)->toBe(1)
        ->and($xpath->query($workflow.'//input[@type="hidden"][@name="default_notes_visible_to_advisor"]')->length)->toBe(1);
});

test('the note visibility field keeps its legacy markup outside settings', function (): void {
    $html = view('operations.repair-orders.partials.repair-order-note-privacy-field', ['inputId' => 'note-test'])->render();

    expect($html)->toContain('ops-note-visibility--advisor')
        ->toContain('name="visible_to_advisor"')
        ->not->toContain('form-selectgroup');
});

test('a printing save round-trips through the tabler form without dropping other settings', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));
    ShopSettings::current()->persistTrusted(['oil_change_sticker_next_due_months' => 9, 'qz_printing_oil_sticker_printer' => 'Oil Queue']);

    $this->from(route('operations.settings.shop.edit', ['section' => 'printing']))
        ->patch(route('operations.settings.shop.printing.update'), [
            'qz_printing_enabled' => '1',
            'qz_printing_key_tag_printer' => 'Brother QL-810W',
            'qz_printing_oil_sticker_printer' => 'Oil Queue',
            'qz_key_tag_orientation' => 'portrait',
            'qz_key_tag_vin_display' => 'last8',
            'qz_key_tag_media_type' => 'mono',
            'key_tag_mileage_requirement' => 'none',
            'oil_change_sticker_next_due_months' => 9,
            'oil_change_interval_miles' => 6000,
        ])
        ->assertRedirect(route('operations.settings.shop.edit', ['section' => 'printing']))
        ->assertSessionHas('status');

    $xpath = shopSettingsXpath($this->get(route('operations.settings.shop.edit', ['section' => 'printing']))->assertOk()->getContent());
    $form = '//form[@action="'.route('operations.settings.shop.printing.update').'"]';

    expect($xpath->query($form.'//input[@name="qz_printing_key_tag_printer"]/@value')->item(0)?->nodeValue)->toBe('Brother QL-810W')
        ->and($xpath->query($form.'//input[@name="qz_printing_oil_sticker_printer"]/@value')->item(0)?->nodeValue)->toBe('Oil Queue')
        ->and($xpath->query($form.'//input[@name="oil_change_interval_miles"]/@value')->item(0)?->nodeValue)->toBe('6000')
        ->and($xpath->query($form.'//input[@name="oil_change_sticker_next_due_months"]/@value')->item(0)?->nodeValue)->toBe('9')
        ->and($xpath->query($form.'//select[@name="qz_key_tag_vin_display"]/option[@selected]/@value')->item(0)?->nodeValue)->toBe('last8');
});

test('a rejected printing save shows the message on the field and keeps the input', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $html = $this->from(route('operations.settings.shop.edit', ['section' => 'printing']))
        ->followingRedirects()
        ->patch(route('operations.settings.shop.printing.update'), [
            'qz_printing_key_tag_printer' => 'Typed but not saved',
            'oil_change_interval_miles' => 500,
            'oil_change_sticker_next_due_months' => 6,
        ])
        ->assertOk()
        ->getContent();

    $xpath = shopSettingsXpath($html);
    $field = $xpath->query('//input[@name="oil_change_interval_miles"]')->item(0);

    expect($field->getAttribute('class'))->toContain('is-invalid')
        ->and($field->getAttribute('value'))->toBe('500')
        ->and(trim($xpath->query('following-sibling::*['.shopSettingsHasClass('invalid-feedback').']', $field)->item(0)?->textContent ?? ''))->not->toBe('')
        ->and($xpath->query('//input[@name="qz_printing_key_tag_printer"]/@value')->item(0)?->nodeValue)->toBe('Typed but not saved')
        ->and(ShopSettings::current()->fresh()->qz_printing_key_tag_printer)->not->toBe('Typed but not saved');
});

test('staff without settings access cannot open or save the migrated sections', function (ArkRole $role): void {
    $before = ShopSettings::current()->oil_change_interval_miles;
    $this->actingAs(actingAsLearnCurrentStaff($role));

    $this->get(route('operations.settings.shop.edit', ['section' => 'workflow']))
        ->assertRedirect(route('operations.index'));

    $this->patch(route('operations.settings.shop.printing.update'), [
        'oil_change_interval_miles' => 7000,
        'oil_change_sticker_next_due_months' => 6,
    ])->assertRedirect(route('operations.index'));

    expect(ShopSettings::current()->fresh()->oil_change_interval_miles)->toBe($before);
})->with([ArkRole::Advisor, ArkRole::Technician]);
