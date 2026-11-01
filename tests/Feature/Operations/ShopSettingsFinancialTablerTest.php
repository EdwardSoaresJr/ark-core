<?php

use App\Ark\Operations\EstimatePricing\LaborPolicy;
use App\Ark\Operations\Settings\BillingClassPillColor;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

function financialSettingsXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

function financialSettingsHasClass(string $class): string
{
    return 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
}

/**
 * Builds the request body a browser would send for the rendered form, as a list of name/value pairs.
 *
 * @return list<array{0: string, 1: string}>
 */
function financialSettingsFormPairs(DOMXPath $xpath, DOMElement $form): array
{
    $pairs = [];

    foreach ($xpath->query('.//input[@name] | .//select[@name] | .//textarea[@name]', $form) as $field) {
        if ($field->hasAttribute('disabled') || $xpath->query('ancestor::template', $field)->length > 0) {
            continue;
        }

        $name = $field->getAttribute('name');

        if ($field->tagName === 'select') {
            $selected = $xpath->query('.//option[@selected]', $field)->item(0) ?? $xpath->query('.//option', $field)->item(0);
            $pairs[] = [$name, $selected?->getAttribute('value') ?? ''];

            continue;
        }

        if ($field->tagName === 'textarea') {
            $pairs[] = [$name, $field->textContent];

            continue;
        }

        $type = strtolower($field->getAttribute('type') ?: 'text');

        if (in_array($type, ['checkbox', 'radio'], true) && ! $field->hasAttribute('checked')) {
            continue;
        }

        $pairs[] = [$name, $field->getAttribute('value')];
    }

    return $pairs;
}

/**
 * @param  list<array{0: string, 1: string}>  $pairs
 * @return array<string, mixed>
 */
function financialSettingsPayload(array $pairs): array
{
    parse_str(implode('&', array_map(fn (array $pair): string => rawurlencode($pair[0]).'='.rawurlencode($pair[1]), $pairs)), $payload);

    return $payload;
}

test('financial rules is a tabler card outside the legacy island with every tab wired to its panel', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $xpath = financialSettingsXpath($this->get(route('operations.settings.shop.edit', ['section' => 'financial']))->assertOk()->getContent());
    $card = $xpath->query('//section[@data-settings-section="financial"]['.financialSettingsHasClass('card').']')->item(0);

    expect($card)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('ancestor::*['.financialSettingsHasClass('ark-legacy').']', $card)->length)->toBe(0)
        ->and($xpath->query('.//*['.financialSettingsHasClass('ark-legacy').']', $card)->length)->toBe(0)
        ->and($xpath->query('//*['.financialSettingsHasClass('ark-legacy').']//*[@x-show="active === \'financial\'"]')->length)->toBe(0);

    $tabs = $xpath->query('.//button[@role="tab"]', $card);
    $keys = [];

    foreach ($tabs as $tab) {
        $panelId = $tab->getAttribute('aria-controls');
        $panel = $xpath->query('.//*[@id="'.$panelId.'"][@role="tabpanel"]', $card)->item(0);

        expect($panel)->toBeInstanceOf(DOMElement::class, "{$panelId} is missing")
            ->and($panel->getAttribute('aria-labelledby'))->toBe($tab->getAttribute('id'));

        $keys[] = substr($panelId, strlen('financial-panel-'));
    }

    expect($keys)->toBe(['labor', 'labor-policies', 'tax', 'shop-fees', 'deposits', 'parts', 'billing-classes']);
});

test('financial forms keep their routes, field names, and parts matrix autosave hooks', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $settings = ShopSettings::current();
    $html = $this->get(route('operations.settings.shop.edit', ['section' => 'financial']))->assertOk()->getContent();
    $xpath = financialSettingsXpath($html);
    $section = '//section[@data-settings-section="financial"]';

    foreach ([
        route('operations.settings.shop.labor.update') => ['labor_categories[0][key]', 'labor_categories[0][name]', 'labor_categories[0][rate]', 'labor_categories[0][minimum_hours]', 'labor_categories[0][rounding_rule]', 'labor_categories[0][allows_modifiers]', 'default_labor_category_key'],
        route('operations.settings.shop.tax.update') => ['tax_enabled', 'tax_label', 'default_tax_rate', 'taxable_labor', 'taxable_parts', 'taxable_shop_fees'],
        route('operations.settings.shop.fees.update') => ['shop_fee_enabled', 'shop_fee_rate', 'shop_fee_cap'],
        route('operations.settings.shop.deposits.update') => ['default_deposit_enabled', 'default_deposit_include_parts', 'default_deposit_include_diagnostics', 'default_deposit_diagnostic_labor_category_keys[]'],
        route('operations.settings.shop.customer-types.update') => ['customer_types[0][document_presentation_profile]', 'customer_types[0][name]', 'customer_types[0][pill_color]', 'customer_types[0][shop_fees_enabled]', 'customer_types[0][shop_fee_rate_override]', 'customer_types[0][default_parts_matrix_key]', 'customer_types[0][discount_type]', 'customer_types[0][discount_amount]'],
        route('operations.settings.shop.parts-matrices.update', $settings->defaultPartsMatrix()['key']) => ['parts_matrices[0][key]', 'parts_matrices[0][name]', 'default_parts_matrix_key'],
    ] as $action => $fields) {
        foreach ($fields as $field) {
            expect($xpath->query($section.'//form[@action="'.$action.'"]//*[@name="'.$field.'"]')->length)
                ->toBeGreaterThan(0, "{$field} is missing from {$action}");
        }
    }

    expect($xpath->query($section.'//form[@id="parts-matrix-form"]')->length)->toBe(1)
        ->and($xpath->query($section.'//form[@action="'.route('operations.settings.shop.labor-policies.update').'"]')->length)->toBe(1)
        ->and(preg_match_all('/<input data-matrix-name-input name="parts_matrices\[\d+\]\[name\]" x-model="matrixName" @input="autosaveMatrix\(/', $html))->toBe(count($settings->partsMatrices()));

    expect($html)
        ->toContain("const form = document.getElementById('parts-matrix-form');")
        ->toContain("fetch(`/app/settings/shop/parts-matrices/\${matrixKey}`, {\n                        method: 'POST',\n                        body: new FormData(form),")
        ->toContain('}, immediate ? 0 : 700);')
        ->toContain('`parts_matrices[0][rows][${rowIndex}][markup_percentage]`');
});

test('submitting each rendered financial form unchanged keeps every stored value', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    ShopSettings::current()->update([
        'tax_enabled' => true,
        'default_tax_rate' => '8.250',
        'taxable_labor' => false,
        'taxable_parts' => true,
        'shop_fee_enabled' => true,
        'default_deposit_enabled' => true,
        'default_deposit_include_parts' => false,
        'default_deposit_include_diagnostics' => true,
        'default_deposit_diagnostic_labor_category_keys' => ['diagnostic', 'programming'],
    ]);

    $stored = fn (): array => collect(ShopSettings::current()->fresh()->getAttributes())->except(['updated_at'])->all();

    // Saving stores labor categories sorted by key and writes each billing class's resolved pill color.
    $meaning = function (array $attributes): array {
        $attributes['labor_categories'] = collect(json_decode($attributes['labor_categories'], true))->sortBy('key')->values()->all();
        $attributes['customer_types'] = collect(json_decode($attributes['customer_types'], true))
            ->map(fn (array $type): array => collect($type)->except('pill_color')->all())
            ->all();

        return $attributes;
    };

    $submitRenderedForms = function (): void {
        $xpath = financialSettingsXpath($this->get(route('operations.settings.shop.edit', ['section' => 'financial']))->assertOk()->getContent());

        foreach (['labor', 'tax', 'fees', 'deposits', 'customer-types'] as $form) {
            $action = route('operations.settings.shop.'.$form.'.update');
            $element = $xpath->query('//section[@data-settings-section="financial"]//form[@action="'.$action.'"]')->item(0);

            expect($element)->toBeInstanceOf(DOMElement::class);

            $this->from(route('operations.settings.shop.edit', ['section' => 'financial']))
                ->post($action, financialSettingsPayload(financialSettingsFormPairs($xpath, $element)))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status');
        }
    };

    $before = $stored();
    $submitRenderedForms();
    $afterFirstSave = $stored();
    $submitRenderedForms();

    expect($meaning($afterFirstSave))->toBe($meaning($before))
        ->and(collect(json_decode($afterFirstSave['customer_types'], true))->pluck('pill_color')->all())
        ->toBe(collect(json_decode($before['customer_types'], true))->map(fn (array $type): string => BillingClassPillColor::resolve($type['pill_color'] ?? null, $type['name']))->all())
        ->and($stored())->toBe($afterFirstSave);
});

test('a rejected tax save shows the message on the rate field and keeps the typed values', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));
    $before = ShopSettings::current()->fresh()->getAttributes();

    $html = $this->from(route('operations.settings.shop.edit', ['section' => 'financial', 'financial-tab' => 'tax']))
        ->followingRedirects()
        ->patch(route('operations.settings.shop.tax.update'), [
            'tax_enabled' => '1',
            'tax_label' => 'Typed label',
            'default_tax_rate' => 'abc',
            'taxable_labor' => '1',
            'taxable_parts' => '1',
            'taxable_shop_fees' => '0',
        ])
        ->assertOk()
        ->getContent();

    $xpath = financialSettingsXpath($html);
    $rate = $xpath->query('//input[@id="settings-tax-rate"]')->item(0);

    expect($rate->getAttribute('class'))->toContain('is-invalid')
        ->and($rate->getAttribute('value'))->toBe('abc')
        ->and(trim($xpath->query('following-sibling::*['.financialSettingsHasClass('invalid-feedback').']', $rate)->item(0)?->textContent ?? ''))->not->toBe('')
        ->and($xpath->query('//input[@id="settings-tax-label"]/@value')->item(0)?->nodeValue)->toBe('Typed label')
        ->and(ShopSettings::current()->fresh()->getAttributes())->toMatchArray([
            'default_tax_rate' => $before['default_tax_rate'],
            'tax_label' => $before['tax_label'],
        ]);
});

test('staff without settings access cannot open financial rules or save any financial form', function (ArkRole $role): void {
    $before = collect(ShopSettings::current()->fresh()->getAttributes())->except(['updated_at'])->all();
    $policies = LaborPolicy::query()->count();
    $this->actingAs(actingAsLearnCurrentStaff($role));

    $this->get(route('operations.settings.shop.edit', ['section' => 'financial', 'financial-tab' => 'labor']))
        ->assertRedirect(route('operations.index'));

    foreach ([
        route('operations.settings.shop.tax.update') => ['tax_enabled' => '1', 'tax_label' => 'Nope', 'default_tax_rate' => '1.000'],
        route('operations.settings.shop.fees.update') => ['shop_fee_enabled' => '1', 'shop_fee_rate' => '9.000', 'shop_fee_cap' => '99.00'],
        route('operations.settings.shop.deposits.update') => ['default_deposit_enabled' => '0'],
        route('operations.settings.shop.customer-types.update') => ['customer_types' => [['name' => 'Nope']]],
        route('operations.settings.shop.labor-policies.update') => ['billing_posture' => 'customer_pay', 'operation_class_key' => 'maintenance', 'hourly_rate' => '1.00', 'effective_from' => '2000-01-01'],
        route('operations.settings.shop.parts-matrices.update', 'aft-parts') => ['parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES],
    ] as $action => $payload) {
        $this->patch($action, $payload)->assertRedirect(route('operations.index'));
    }

    $this->delete(route('operations.settings.shop.parts-matrices.destroy', 'aft-parts'))
        ->assertRedirect(route('operations.index'));

    expect(collect(ShopSettings::current()->fresh()->getAttributes())->except(['updated_at'])->all())->toBe($before)
        ->and(LaborPolicy::query()->count())->toBe($policies);
})->with([ArkRole::Advisor, ArkRole::Technician]);
