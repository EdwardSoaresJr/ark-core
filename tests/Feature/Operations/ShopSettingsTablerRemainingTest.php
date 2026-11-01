<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

function remainingSettingsXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

function remainingSettingsHasClass(string $class): string
{
    return 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
}

function remainingSettingsPage(object $test, string $section = 'general'): DOMXPath
{
    $test->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    return remainingSettingsXpath($test->get(route('operations.settings.shop.edit', ['section' => $section]))->assertOk()->getContent());
}

test('every remaining section is a tabler card and only the telephony island stays legacy', function (): void {
    $xpath = remainingSettingsPage($this);
    $legacy = remainingSettingsHasClass('ark-legacy');

    foreach (['operations', 'staff', 'excellence', 'payments', 'partstech', 'communications', 'ark-cloud', 'runtime-health'] as $section) {
        $cards = $xpath->query('//section[@data-settings-section="'.$section.'"]['.remainingSettingsHasClass('card').']');

        expect($cards->length)->toBe(1, "{$section} is not one card")
            ->and($cards->item(0)->getAttribute('x-show'))->toBe("active === '{$section}'")
            ->and($cards->item(0)->hasAttribute('x-cloak'))->toBeTrue()
            ->and($xpath->query('ancestor::*['.$legacy.']', $cards->item(0))->length)->toBe(0, "{$section} is inside a legacy island");
    }

    $islands = $xpath->query('//section[@data-settings-section]//*['.$legacy.']');

    expect($islands->length)->toBe(1)
        ->and($islands->item(0)->hasAttribute('data-settings-telephony-island'))->toBeTrue()
        ->and($xpath->query('ancestor::section[@data-settings-section="communications"]', $islands->item(0))->length)->toBe(1)
        ->and($xpath->query('.//section[@x-show="active === \'communications\'"]', $islands->item(0))->length)->toBe(1)
        ->and($xpath->query('//*['.$legacy.'][contains(@x-show, "includes(active)")]')->length)->toBe(0)
        ->and($xpath->query('//*[@x-show][starts-with(@x-show, "active === ")][not(self::section[@data-settings-section])][not(ancestor::*['.$legacy.'])]')->length)->toBe(0);
});

test('the remaining settings forms keep their routes and field names', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($admin);
    $xpath = remainingSettingsXpath($this->get(route('operations.settings.shop.edit', ['section' => 'operations']))->assertOk()->getContent());

    $forms = [
        'operations' => [
            route('operations.settings.shop.appointments.update') => ['_method', 'appointments_enabled', 'appointment_slot_minutes', 'appointment_capacity_basis', 'appointment_scheduling_target_percent', 'appointment_capacity_enforcement', 'scheduling_hours_follow_shop', 'scheduling_hours[monday][enabled]', 'scheduling_hours[monday][open]', 'scheduling_hours[sunday][close]', 'appointment_request_availability[weekly][monday][enabled]', 'appointment_request_availability[horizon_is_custom]', 'appointment_request_availability[horizon_days]', 'appointment_request_availability[horizon_custom_days]', 'appointment_request_availability[minimum_notice_days]', 'appointment_request_availability[request_windows][morning][enabled]', 'appointment_request_availability[request_windows][afternoon][close]', 'appointment_request_availability[request_windows][flexible_enabled]', 'appointment_request_availability[request_windows][latest_appointment_arrival_enabled]', 'appointment_request_availability[request_windows][latest_appointment_arrival]', 'workstation_idle_lock_minutes'],
            route('operations.settings.shop.appointments.bays.store') => ['name', 'location_label'],
            route('operations.settings.shop.operational-profile.apply') => ['operational_profile'],
            route('operations.settings.shop.import.preview') => ['csv'],
        ],
        'staff' => [
            route('operations.settings.staff.store') => ['name', 'email', 'phone', 'roles[]', 'labor_cost', 'labor_pay_basis', 'flag_rate', 'floor_rate', 'workday_hours'],
            route('operations.settings.staff.update', $admin) => ['_method', '_member', 'name', 'roles[]', 'email', 'phone', 'operator_pin', 'labor_cost', 'labor_pay_basis', 'workday_hours', 'password'],
        ],
        'excellence' => [
            route('operations.settings.shop.excellence.update') => ['_method', 'effective_labor_rate_floor', 'aro_target', 'parts_margin_target_percent', 'monthly_fixed_costs', 'labor_sales_target_percent', 'parts_sales_target_percent', 'net_profit_target_percent', 'income_tax_reserve_percent', 'payroll_tax_reserve_percent', 'monthly_payroll_tax', 'opportunity_ros_per_open_day', 'dollar_close_target_percent', 'sold_labor_hours_per_open_day', 'median_cycle_target_days', 'stalled_ro_age_days', 'parts_margin_target_active', 'owner_digest_enabled', 'owner_digest_time', 'mark_target_reviewed'],
        ],
        'payments' => [
            route('operations.settings.shop.payments.update') => ['_method', 'square_terminal_device_id', 'square_terminal_enabled', 'square_keyed_enabled', 'square_portal_pay_enabled', 'square_email_pay_enabled'],
            route('operations.settings.shop.manual-payment-methods.update') => ['_method'],
        ],
        'partstech' => [
            route('operations.settings.shop.partstech.update') => ['_method', 'partstech_color'],
            route('operations.settings.shop.repairlink.update') => ['_method', 'repairlink_enabled', 'repairlink_url', 'repairlink_color'],
            route('operations.settings.shop.nexpart.update') => ['_method', 'nexpart_enabled', 'nexpart_url', 'nexpart_color'],
            route('operations.settings.shop.catalog-links.update') => ['_method', 'create[label]', 'create[url]', 'create[mode]', 'create[color]'],
        ],
        'ark-cloud' => [
            route('operations.settings.shop.ark-cloud.connect') => [],
            route('operations.settings.shop.ark-cloud.connect-manual') => [],
        ],
    ];

    foreach ($forms as $section => $actions) {
        foreach ($actions as $action => $fields) {
            $form = '//section[@data-settings-section="'.$section.'"]//form[@action="'.$action.'"]';

            expect($xpath->query($form)->length)->toBeGreaterThan(0, "{$action} is missing from {$section}");

            foreach ($fields as $field) {
                expect($xpath->query($form.'//*[@name="'.$field.'"]')->length)->toBeGreaterThan(0, "{$field} is missing from {$action}");
            }
        }
    }

    foreach ([
        'appointments_enabled',
        'scheduling_hours_follow_shop',
        'scheduling_hours[monday][enabled]',
        'appointment_request_availability[request_windows][flexible_enabled]',
        'square_terminal_enabled',
        'square_email_pay_enabled',
        'repairlink_enabled',
        'nexpart_enabled',
    ] as $field) {
        $checkbox = $xpath->query('//input[@type="checkbox"][@name="'.$field.'"]')->item(0);

        expect($checkbox)->toBeInstanceOf(DOMElement::class)
            ->and($xpath->query('preceding-sibling::input[@type="hidden"][@name="'.$field.'"][@value="0"]', $checkbox)->length)->toBe(1, "{$field} lost its hidden 0");
    }

    $manualMethods = $xpath->query('//section[@data-settings-section="payments"]//form[@action="'.route('operations.settings.shop.manual-payment-methods.update').'"]')->item(0);

    expect($manualMethods->getAttribute('x-data'))->toStartWith('{ methods: ')
        ->and($xpath->query('.//template[@x-for="(method, index) in methods"]', $manualMethods)->length)->toBe(1)
        ->and($xpath->query('.//input[@x-model="method.label"][@maxlength="24"]', $manualMethods)->length)->toBe(1);

    expect($xpath->query('//section[@data-settings-section="runtime-health"]//*[@data-runtime-operational-clock][starts-with(@x-data, "arkOperationalClock(")]')->length)->toBe(1);
});

test('links in the remaining sections still switch sections through the root scope', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));
    $html = $this->get(route('operations.settings.shop.edit', ['section' => 'excellence']))->assertOk()->getContent();
    $excellence = str($html)->after('data-settings-section="excellence"')->before('data-settings-section=')->toString();

    expect($excellence)
        ->toContain('href="'.e(route('operations.settings.shop.edit', ['section' => 'financial', 'financial-tab' => 'labor'])).'" @click.prevent="setActive(\'financial\'); setFinancialTab(\'labor\')"')
        ->toContain('href="'.route('operations.settings.shop.edit', ['section' => 'overhead']).'" @click.prevent="setActive(\'overhead\')"')
        ->and($html)->not->toContain('$parent')
        ->toContain('@click.prevent="setActive(\'runtime-health\')"');
});

test('the staff calculator stays inside each staff form', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $xpath = remainingSettingsPage($this, 'staff');

    foreach ([route('operations.settings.staff.store') => 'staff-labor-cost', route('operations.settings.staff.update', $admin) => null] as $action => $target) {
        $form = '//section[@data-settings-section="staff"]//form[@action="'.$action.'"]';
        $calculator = $xpath->query($form.'//*[@data-loaded-labor-calculator]')->item(0);

        expect($calculator)->toBeInstanceOf(DOMElement::class)
            ->and($calculator->getAttribute('x-data'))->toStartWith('loadedLaborCostCalculator({');

        if ($target !== null) {
            expect($calculator->getAttribute('x-data'))->toContain("targetInputId: '{$target}'");
        }
    }
});

test('staff without settings access cannot open or save the remaining sections', function (ArkRole $role): void {
    $settings = ShopSettings::current();
    $before = [
        'aro' => ShopExcellenceTargets::current()['aro_target_cents'],
        'slot' => $settings->appointment_slot_minutes,
        'terminal' => $settings->square_terminal_device_id,
        'repairlink' => $settings->repairlink_url,
    ];
    $userCount = User::query()->count();
    $this->actingAs(actingAsLearnCurrentStaff($role));

    foreach (['operations', 'staff', 'excellence', 'payments', 'partstech', 'communications', 'ark-cloud', 'runtime-health'] as $section) {
        $this->get(route('operations.settings.shop.edit', ['section' => $section]))
            ->assertRedirect(route('operations.index'));
    }

    $this->patch(route('operations.settings.shop.excellence.update'), ['aro_target' => '999.00'])->assertRedirect(route('operations.index'));
    $this->patch(route('operations.settings.shop.appointments.update'), ['appointment_slot_minutes' => 15])->assertRedirect(route('operations.index'));
    $this->patch(route('operations.settings.shop.payments.update'), ['square_terminal_device_id' => 'INTRUDER'])->assertRedirect(route('operations.index'));
    $this->patch(route('operations.settings.shop.repairlink.update'), ['repairlink_enabled' => '1', 'repairlink_url' => 'https://intruder.test'])->assertRedirect(route('operations.index'));
    $this->post(route('operations.settings.staff.store'), ['name' => 'Intruder', 'email' => 'intruder@example.test', 'roles' => [ArkRole::Admin->value]])->assertRedirect(route('operations.index'));

    $fresh = ShopSettings::current()->fresh();

    expect(ShopExcellenceTargets::current()['aro_target_cents'])->toBe($before['aro'])
        ->and($fresh->appointment_slot_minutes)->toBe($before['slot'])
        ->and($fresh->square_terminal_device_id)->toBe($before['terminal'])
        ->and($fresh->repairlink_url)->toBe($before['repairlink'])
        ->and(User::query()->count())->toBe($userCount + 1)
        ->and(User::query()->where('email', 'intruder@example.test')->exists())->toBeFalse();
})->with([ArkRole::Advisor, ArkRole::Technician]);
