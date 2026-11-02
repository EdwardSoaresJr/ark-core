<?php

use App\Ark\Operations\Documents\PdfRuntimePaths;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Spatie\Browsershot\Browsershot;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

function overheadSettingsXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

function overheadSettingsHasClass(string $class): string
{
    return 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
}

/**
 * @return array<string, mixed>
 */
function overheadSettingsCalculatorPayload(): array
{
    return [
        'fixed_cost_lines' => [
            ['label' => 'Rent', 'amount' => '3000', 'period' => 'monthly'],
            ['label' => 'Payroll', 'amount' => '1500', 'period' => 'weekly'],
            ['label' => 'Insurance', 'amount' => '2400', 'period' => 'annual'],
        ],
        'monthly_card_volume' => '85000',
        'card_processing_percent' => '2.9',
        'merchant_financing_holdback_percent' => '8',
        'fixed_monthly_financing_payment' => '',
        'technician_count' => '3',
        'workdays_per_month' => '22',
        'workday_hours' => '8',
        'billable_utilization' => '85',
        'overhead_tab' => 'fixed-costs',
    ];
}

test('shop overhead is a tabler card outside the legacy island with its three tabs wired to panels', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $xpath = overheadSettingsXpath($this->get(route('operations.settings.shop.edit', ['section' => 'overhead']))->assertOk()->getContent());
    $card = $xpath->query('//section[@data-settings-section="overhead"]['.overheadSettingsHasClass('card').']')->item(0);

    expect($card)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('ancestor::*['.overheadSettingsHasClass('ark-legacy').']', $card)->length)->toBe(0)
        ->and($xpath->query('.//*['.overheadSettingsHasClass('ark-legacy').']', $card)->length)->toBe(0)
        ->and($xpath->query('//*['.overheadSettingsHasClass('ark-legacy').']//*[@x-show="active === \'overhead\'"]')->length)->toBe(0);

    $panels = [];

    foreach ($xpath->query('.//button[@role="tab"]', $card) as $tab) {
        $panel = $xpath->query('.//*[@id="'.$tab->getAttribute('aria-controls').'"][@role="tabpanel"]', $card)->item(0);

        expect($panel)->toBeInstanceOf(DOMElement::class)
            ->and($panel->getAttribute('aria-labelledby'))->toBe($tab->getAttribute('id'));

        $panels[] = $panel->getAttribute('x-show');
    }

    expect($panels)->toBe([
        "overheadTab === 'fixed-costs'",
        "overheadTab === 'payments'",
        "overheadTab === 'capacity'",
    ]);
});

test('the overhead calculator keeps its bindings, outputs, and save hook', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $html = $this->get(route('operations.settings.shop.edit', ['section' => 'overhead']))->assertOk()->getContent();
    $section = str($html)->after('data-settings-section="overhead"')->before('data-settings-section=')->toString();

    expect($section)
        ->toContain('action="'.route('operations.settings.shop.overhead.update').'"')
        ->toContain('@submit.prevent="saveOverhead()"')
        ->toContain('x-data="shopOverheadCalculator({')
        ->toContain('<template x-for="(line, index) in fixedCostLines" :key="line._key ?? index">')
        ->toContain('@click="addFixedCostLine()"')
        ->toContain('@click="removeFixedCostLine(index)"');

    foreach (['line.label', 'line.amount', 'line.period', 'monthlyCardVolume', 'cardProcessingPercent', 'merchantFinancingHoldbackPercent', 'fixedMonthlyFinancingPayment', 'technicianCount', 'workdaysPerMonth', 'workdayHours', 'billableUtilization'] as $model) {
        expect(substr_count($section, 'x-model="'.$model.'"'))->toBe(1, "{$model} is not bound once");
    }

    foreach (['formattedOverheadPerHour()', 'money(monthlyEquivalentForLine(line))', 'money(monthlyFixedOverheadTotal())', 'money(monthlyProcessingCost())', 'money(monthlyFinancingCost())', 'money(monthlyPaymentOverheadTotal())', 'money(monthlyOverheadTotal())', '`${monthlyBillableHours()} hr`'] as $output) {
        expect($section)->toContain('x-text="'.$output.'"');
    }
});

test('the settings to staff links open the staff section without $parent', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $html = $this->get(route('operations.settings.shop.edit', ['section' => 'overhead']))->assertOk()->getContent();
    $xpath = overheadSettingsXpath($html);
    $links = $xpath->query('//section[@data-settings-section="overhead"]//a[@data-overhead-staff-link]');

    expect($html)->not->toContain('$parent')
        ->and($links->length)->toBe(2)
        ->and(substr_count($html, '@click.prevent="setActive(\'staff\')" data-overhead-staff-link'))->toBe(2);

    foreach ($links as $link) {
        expect($link->getAttribute('href'))->toBe(route('operations.settings.shop.edit', ['section' => 'staff']))
            ->and(trim($link->textContent))->toBe('Settings → Staff');
    }
});

test('clicking each settings to staff link on shop overhead opens the staff section in a browser', function (): void {
    $chrome = PdfRuntimePaths::resolveChromePath();
    $node = PdfRuntimePaths::resolveNodeBinary();
    $nodeModules = collect([
        base_path('node_modules'),
        getenv('PUPPETEER_NODE_MODULES') ?: null,
    ])->first(fn ($path) => is_string($path) && is_dir($path.DIRECTORY_SEPARATOR.'puppeteer'));
    $alpine = base_path('node_modules/alpinejs/dist/cdn.min.js');

    if (
        ! is_string($chrome) || $chrome === '' || ! is_executable($chrome)
        || ! is_string($node) || $node === '' || ! is_executable($node)
        || ! is_string($nodeModules) || ! is_file($alpine)
    ) {
        $this->markTestSkipped('Chromium, Node, puppeteer, and Alpine are required to click the links in a browser.');
    }

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $html = $this->get(route('operations.settings.shop.edit', ['section' => 'overhead']))->assertOk()->getContent();
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
    $html = preg_replace('#<link\b[^>]*>#i', '', $html);
    $calculator = str_replace(
        'export function shopOverheadCalculator',
        'function shopOverheadCalculator',
        (string) file_get_contents(resource_path('js/ark-shop-overhead-calculator.js')),
    );
    $html = str_replace(
        '</body>',
        '<script>'.$calculator.'window.shopOverheadCalculator = shopOverheadCalculator;</script><script>'.file_get_contents($alpine).'</script></body>',
        $html,
    );

    $browser = Browsershot::html($html)
        ->windowSize(1440, 900)
        ->setChromePath($chrome)
        ->setNodeBinary($node)
        ->setNodeModulePath($nodeModules);

    if ($npm = PdfRuntimePaths::resolveNpmBinary($node)) {
        $browser->setNpmBinary($npm);
    }

    if (config('services.pdf.no_sandbox')) {
        $browser->noSandbox();
    }

    $payload = json_decode($browser->evaluate(<<<'JS'
        (async () => {
            const settle = () => new Promise((resolve) => setTimeout(resolve, 50));
            const links = [...document.querySelectorAll('[data-overhead-staff-link]')];
            const results = [];

            for (const link of links) {
                const scope = Alpine.$data(link);
                scope.setActive('overhead');
                await settle();
                const overheadShownBefore = document.querySelector('[data-settings-section="overhead"]').style.display !== 'none';
                link.click();
                await settle();
                results.push({
                    overheadShownBefore,
                    active: scope.active,
                    section: new URL(window.location.href).searchParams.get('section'),
                    staffShown: [...document.querySelectorAll('[x-show]')].some((el) => el.getAttribute('x-show') === "active === 'staff'" && el.style.display !== 'none'),
                    overheadHidden: document.querySelector('[data-settings-section="overhead"]').style.display === 'none',
                });
            }

            return JSON.stringify({ count: links.length, results });
        })()
        JS), true);

    expect($payload['count'])->toBe(2);

    foreach ($payload['results'] as $result) {
        expect($result)->toBe([
            'overheadShownBefore' => true,
            'active' => 'staff',
            'section' => 'staff',
            'staffShown' => true,
            'overheadHidden' => true,
        ]);
    }
});

test('a calculator save stores the overhead, the rate per billed hour, and owner targets fixed costs', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->patchJson(route('operations.settings.shop.overhead.update'), overheadSettingsCalculatorPayload())
        ->assertOk()
        ->assertJsonPath('message', 'Shop overhead saved.')
        ->assertJsonPath('overhead_per_hour', 41.82)
        ->assertJsonPath('monthly_fixed_costs', 9700);

    $settings = ShopSettings::current()->fresh();

    expect($settings->shop_overhead_per_hour_cents)->toBe(4182)
        ->and(ShopExcellenceTargets::current()['monthly_fixed_costs_cents'])->toBe(970000)
        ->and($settings->shopOverheadStateArray()['fixed_cost_lines'])->toBe(overheadSettingsCalculatorPayload()['fixed_cost_lines']);

    $this->get(route('operations.settings.shop.edit', ['section' => 'overhead']))
        ->assertOk()
        ->assertSee('Insurance')
        ->assertSee('85000');
});

test('a rejected overhead save returns the field message and stores nothing', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));
    $before = collect(ShopSettings::current()->fresh()->getAttributes())->except(['updated_at'])->all();

    $this->patchJson(route('operations.settings.shop.overhead.update'), array_merge(overheadSettingsCalculatorPayload(), [
        'monthly_card_volume' => str_repeat('1', 33),
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['monthly_card_volume' => 'must not be greater than 32 characters']);

    expect(collect(ShopSettings::current()->fresh()->getAttributes())->except(['updated_at'])->all())->toBe($before);
});

test('staff without settings access cannot open or save shop overhead', function (ArkRole $role): void {
    $before = collect(ShopSettings::current()->fresh()->getAttributes())->except(['updated_at'])->all();
    $this->actingAs(actingAsLearnCurrentStaff($role));

    $this->get(route('operations.settings.shop.edit', ['section' => 'overhead']))
        ->assertRedirect(route('operations.index'));

    $this->patch(route('operations.settings.shop.overhead.update'), overheadSettingsCalculatorPayload())
        ->assertRedirect(route('operations.index'));

    $this->patchJson(route('operations.settings.shop.overhead.update'), overheadSettingsCalculatorPayload())
        ->assertForbidden();

    expect(collect(ShopSettings::current()->fresh()->getAttributes())->except(['updated_at'])->all())->toBe($before);
})->with([ArkRole::Advisor, ArkRole::Technician]);
