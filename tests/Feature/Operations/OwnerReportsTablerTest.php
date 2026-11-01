<?php

use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-09-24 18:00:00', 'America/Denver'));
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function ownerReportsXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

function ownerReportsClass(string $class): string
{
    return 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
}

/**
 * The page renders in the Tabler shell, and nothing inside its page body is a legacy island.
 */
function ownerReportsAssertTablerPage(string $html, string $title): DOMXPath
{
    $xpath = ownerReportsXpath($html);
    $body = $xpath->query('//*['.ownerReportsClass('page-body').']')->item(0);

    expect($html)->toContain('id="ark-sidebar-menu"')
        ->and($xpath->query('//h2['.ownerReportsClass('page-title').'][contains(normalize-space(.), "'.$title.'")]')->length)->toBe(1)
        ->and($body)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('.//*['.ownerReportsClass('ark-legacy').']', $body)->length)->toBe(0)
        ->and($xpath->query('.//*[contains(@class, "ops-")]', $body)->length)->toBe(0);

    return $xpath;
}

function ownerReportsAdmin(): User
{
    $admin = User::factory()->create(['name' => 'Alex Rivera'])->assignRole(ArkRole::Admin->value);
    completeRequiredLearnFor($admin);

    return $admin;
}

test('business cockpit renders on tabler with yesterday and its report links', function (): void {
    $admin = ownerReportsAdmin();

    $html = $this->actingAs($admin)->get(route('operations.business'))->assertOk()->getContent();
    $xpath = ownerReportsAssertTablerPage($html, 'Alex');

    expect($xpath->query('//*[@data-business-yesterday]['.ownerReportsClass('card').']//h3[normalize-space(.)="Yesterday"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-business-links]//a[@href="'.route('operations.owner.day-review').'"][normalize-space(.)="Day Review"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-business-links]//a[@href="'.route('operations.reports.index').'"][normalize-space(.)="Reports"]')->length)->toBe(1);
});

test('business cockpit stays forbidden to technicians', function (): void {
    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Technician))
        ->get(route('operations.business'))
        ->assertForbidden();
});

test('market pressure panel renders tabler markup with the same figures and link', function (): void {
    $html = view('operations.today.partials.market-pressure-panel', ['panel' => [
        'posture' => 'pressure',
        'headline' => 'Review requests are behind',
        'eligible_closes' => 6,
        'target' => '80%',
        'missed_count' => 2,
        'why_care' => 'Reviews bring new customers.',
        'advisor_breakdown' => [
            ['name' => 'Sam Advisor', 'review_requests' => 3, 'paid_closes' => 4],
        ],
        'detail_url' => 'https://shop.test/market',
    ]])->render();
    $xpath = ownerReportsXpath($html);

    expect($html)->toContain('Review requests are behind')
        ->and($html)->toContain('80%')
        ->and($html)->toContain('2 opportunities')
        ->and($html)->toContain('3/4')
        ->and($html)->not->toContain('text-slate')
        ->and($xpath->query('//*['.ownerReportsClass('badge').'][normalize-space(.)="pressure"]')->length)->toBe(1)
        ->and($xpath->query('//span['.ownerReportsClass('bg-primary').']')->length)->toBe(6)
        ->and($xpath->query('//a[@href="https://shop.test/market"]')->length)->toBe(1);
});

test('scoreboard tab renders on tabler with the dashboard pills, period filter, and drill-down links', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $html = $this->actingAs($advisor)
        ->get(route('operations.owner.scoreboard', ['period' => 'last_7']))
        ->assertOk()
        ->getContent();
    $xpath = ownerReportsAssertTablerPage($html, 'Shop scoreboard');

    $active = $xpath->query('//nav[@aria-label="Dashboard"]['.ownerReportsClass('nav-pills').']//a['.ownerReportsClass('active').']')->item(0);
    expect($active)->toBeInstanceOf(DOMElement::class)
        ->and(trim($active->textContent))->toBe('Scoreboard')
        ->and($active->getAttribute('aria-current'))->toBe('page');

    $form = $xpath->query('//form[@data-scoreboard-period-form][@method="GET"][@action="'.route('operations.owner.scoreboard').'"]')->item(0);
    expect($form)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('.//label[@for="scoreboard-period"]', $form)->length)->toBe(1)
        ->and($xpath->query('.//select[@id="scoreboard-period"][@name="period"]['.ownerReportsClass('form-select').']/option[@value="last_7"][@selected]', $form)->length)->toBe(1);

    expect($xpath->query('//*[@data-scoreboard-kpi]['.ownerReportsClass('card-sm').']')->length)->toBeGreaterThan(0)
        ->and($xpath->query('//*[@data-scoreboard-kpi]//*['.ownerReportsClass('subheader').'][normalize-space(.)="Dollar close"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-scoreboard-authorized]//a[contains(@href, "focus=authorized")]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-scoreboard-queues]//a[contains(@href, "focus=follow_up")]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-scoreboard-cycle]//a[contains(@href, "focus=aging")][contains(@href, "days=2")]')->length)->toBe(1)
        ->and($html)->toContain('How dollar close is counted')
        ->and($html)->not->toContain('href="'.route('operations.settings.shop.edit', ['section' => 'excellence']).'"');
});

test('scoreboard drill-down renders as a tabler card with its close link', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $html = $this->actingAs($advisor)
        ->get(route('operations.owner.scoreboard', ['period' => 'last_7', 'focus' => 'aging', 'days' => 5]))
        ->assertOk()
        ->getContent();
    $xpath = ownerReportsAssertTablerPage($html, 'Shop scoreboard');

    expect($xpath->query('//form[@data-scoreboard-period-form]//input[@type="hidden"][@name="focus"][@value="aging"]')->length)->toBe(1)
        ->and($xpath->query('//form[@data-scoreboard-period-form]//input[@type="hidden"][@name="days"][@value="5"]')->length)->toBe(1)
        ->and($xpath->query('//section[@data-scoreboard-drilldown]['.ownerReportsClass('card').']//a[@href="'.route('operations.owner.scoreboard', ['period' => 'last_7']).'"][normalize-space(.)="Close"]')->length)->toBe(1);
});

test('scoreboard shows the targets link to settings managers', function (): void {
    $admin = ownerReportsAdmin();

    $this->actingAs($admin)
        ->get(route('operations.owner.scoreboard'))
        ->assertOk()
        ->assertSee('href="'.route('operations.settings.shop.edit', ['section' => 'excellence']).'"', false);
});

test('scoreboard stays forbidden to technicians and keeps the operational-only view', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Technician->value))
        ->get(route('operations.owner.scoreboard'))
        ->assertForbidden();

    $operational = User::factory()->create();
    $operational->givePermissionTo([
        ArkCapability::OperationsAccess->value,
        ArkCapability::ScoreboardOperationalView->value,
    ]);

    $html = $this->actingAs($operational)->get(route('operations.owner.scoreboard'))->assertOk()->getContent();
    $xpath = ownerReportsAssertTablerPage($html, 'Shop scoreboard');

    expect($xpath->query('//*[@data-scoreboard-money]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-scoreboard-now]')->length)->toBe(1);
});

test('the scoreboard wall and its refresh fragment keep the legacy board', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.owner.scoreboard', ['display' => 'wall']))
        ->assertOk()
        ->assertDontSee('data-scoreboard-board', false)
        ->assertSee('scoreboard-kpis', false);

    $this->actingAs($advisor)
        ->get(route('operations.owner.scoreboard', ['fragment' => '1']))
        ->assertOk()
        ->assertDontSee('data-scoreboard-board', false)
        ->assertDontSee('nav-pills', false);
});

function ownerReportsTexts(DOMXPath $xpath, string $query): array
{
    $texts = [];
    foreach ($xpath->query($query) as $node) {
        $texts[] = preg_replace('/\s+/u', ' ', trim($node->textContent));
    }

    return $texts;
}

test('reports catalog renders on tabler with every report card link', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);

    $response = $this->actingAs($advisor)->get(route('operations.reports.index'))->assertOk();
    $xpath = ownerReportsAssertTablerPage($response->getContent(), 'Pick a report');

    $cards = collect($response->viewData('sections'))->flatMap(fn (array $section): array => $section['cards']);
    expect($cards)->not->toBeEmpty();

    foreach ($cards as $card) {
        expect($xpath->query('//a[@data-reports-card]['.ownerReportsClass('card-link').'][@href="'.$card['url'].'"]')->length)->toBe(1);
    }

    expect($xpath->query('//*[@data-reports-section]')->length)->toBe(count($response->viewData('sections')));
});

test('operational report renders on tabler with its range form, figures, and section links', function (string $tab): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);

    $response = $this->actingAs($advisor)
        ->get(route('operations.reports.operational', ['tab' => $tab, 'from' => '2026-09-01', 'to' => '2026-09-20']))
        ->assertOk();
    $xpath = ownerReportsAssertTablerPage($response->getContent(), 'Executive Pulse');

    $form = $xpath->query('//form[@method="GET"][@action="'.route('operations.reports.operational').'"]')->item(0);
    expect($form)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('.//input[@type="hidden"][@name="tab"][@value="'.$tab.'"]', $form)->length)->toBe(1)
        ->and($xpath->query('.//input[@type="date"][@name="from"][@id="from"][@value="2026-09-01"][@min]['.ownerReportsClass('form-control').']', $form)->length)->toBe(1)
        ->and($xpath->query('.//input[@type="date"][@name="to"][@id="to"][@value="2026-09-20"]['.ownerReportsClass('form-control').']', $form)->length)->toBe(1)
        ->and($xpath->query('.//label[@for="from"]', $form)->length)->toBe(1);

    $tiles = ownerReportsTexts($xpath, '//*[@data-report-kpi]');
    expect($tiles)->toHaveCount(count($response->viewData('kpis')));
    foreach ($response->viewData('kpis') as $index => $kpi) {
        expect($tiles[$index])->toContain($kpi['label'])->toContain($kpi['value']);
    }

    $nav = $xpath->query('//nav[@data-report-tabs]['.ownerReportsClass('nav-pills').']')->item(0);
    expect($nav)->toBeInstanceOf(DOMElement::class);
    foreach (\App\Ark\Operations\Reports\OperationalReportTab::casesInDisplayOrder() as $case) {
        $link = $xpath->query('.//a[@href="'.route('operations.reports.operational', ['from' => '2026-09-01', 'to' => '2026-09-20', 'tab' => $case->value]).'"]', $nav)->item(0);
        expect($link)->toBeInstanceOf(DOMElement::class)
            ->and($link->getAttribute('aria-current'))->toBe($case->value === $tab ? 'page' : '');
    }

    expect($xpath->query('//*[@data-report-tab="'.$tab.'"]//table['.ownerReportsClass('card-table').']/ancestor::*['.ownerReportsClass('table-responsive').']/ancestor::*['.ownerReportsClass('card').']')->length)->toBeGreaterThan(0)
        ->and($xpath->query('//a[@href="'.route('operations.reports.index').'"]')->length)->toBeGreaterThan(0);
})->with(fn (): array => array_map(
    fn (\App\Ark\Operations\Reports\OperationalReportTab $tab): string => $tab->value,
    \App\Ark\Operations\Reports\OperationalReportTab::casesInDisplayOrder(),
));

test('operational report links one shop day to its end of day card', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);

    $this->actingAs($advisor)
        ->get(route('operations.reports.operational', ['from' => '2026-09-20', 'to' => '2026-09-20']))
        ->assertOk()
        ->assertSee('href="'.route('operations.reports.end-of-day', ['date' => '2026-09-20']).'"', false);

    $this->actingAs(ownerReportsAdmin())
        ->get(route('operations.reports.operational', ['from' => '2026-09-20', 'to' => '2026-09-20']))
        ->assertOk()
        ->assertSee('href="'.route('operations.owner.day-review', ['date' => '2026-09-20']).'"', false);
});

test('end of day report renders on tabler with its figures and shop day filter', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);

    $response = $this->actingAs($advisor)->get(route('operations.reports.end-of-day'))->assertOk();
    $xpath = ownerReportsAssertTablerPage($response->getContent(), 'End of Day');
    $eod = $response->viewData('eod');

    expect($xpath->query('//form[@method="GET"][@action="'.$response->viewData('dateFormAction').'"]//input[@type="date"][@name="date"][@id="bookend-date"][@value="'.$eod->fromDate.'"]['.ownerReportsClass('form-control').']')->length)->toBe(1)
        ->and($xpath->query('//label[@for="bookend-date"]')->length)->toBe(1)
        ->and($xpath->query('//a[@href="'.route('operations.reports.index').'"]')->length)->toBeGreaterThan(0)
        ->and($xpath->query('//*[@data-eod-reconcile]//a[@href="'.$eod->financialUrl.'"]')->length)->toBe(1)
        ->and(ownerReportsTexts($xpath, '//h2[@id="eod-title"]'))->toBe([$eod->rangeLabel]);

    $metrics = ownerReportsTexts($xpath, '//*[@data-eod-metric]');
    $expected = [...$eod->salesEffectiveness, ...$eod->shopMetrics];
    expect($metrics)->toHaveCount(count($expected));
    foreach ($expected as $index => $metric) {
        expect($metrics[$index])->toContain($metric['label'])->toContain($metric['value']);
    }

    $summaryRows = ownerReportsTexts($xpath, '//*[@data-eod-section="ro-summary"]//table['.ownerReportsClass('card-table').']//tr');
    foreach ($eod->roSummary as $index => $row) {
        expect($summaryRows[$index])->toContain($row['label'])->toContain($row['value']);
    }

    $reconcile = ownerReportsTexts($xpath, '//*[@data-eod-reconcile]')[0];
    expect($reconcile)->toContain($eod->reconciliation['sales_posted'])
        ->toContain($eod->reconciliation['write_offs'])
        ->toContain($eod->reconciliation['cash_collected']);
});

test('reports stay forbidden to technicians', function (string $route): void {
    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Technician))
        ->get(route($route))
        ->assertForbidden();
})->with([
    'catalog' => 'operations.reports.index',
    'operational' => 'operations.reports.operational',
    'end of day' => 'operations.reports.end-of-day',
]);

test('day review renders on tabler with the end of day card, queue pressure, and its links', function (): void {
    $response = $this->actingAs(ownerReportsAdmin())
        ->get(route('operations.owner.day-review', ['date' => '2026-09-20']))
        ->assertOk();
    $xpath = ownerReportsAssertTablerPage($response->getContent(), 'Day Review');
    $eod = $response->viewData('eod');

    expect($xpath->query('//*[@data-eod-report]//form[@action="'.route('operations.owner.day-review').'"]//input[@name="date"][@value="2026-09-20"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-day-review-queue]['.ownerReportsClass('card').']//*['.ownerReportsClass('list-group-item').']')->length)->toBeGreaterThan(0)
        ->and($xpath->query('//*[@data-day-review-links]//a[@href="'.route('operations.reports.index').'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-day-review-links]//a[@href="'.$eod->reportUrl.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-day-review-links]//a[@href="'.route('operations.workboard').'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-day-review-links]//a[@href="'.route('operations.learn.show', ['role' => 'owner', 'article' => 'bookend-walkthrough']).'"]')->length)->toBe(1);

    foreach ($response->viewData('priorities') as $priority) {
        expect($response->getContent())->toContain(e($priority['label']));
    }
});

test('day review stays forbidden to advisors', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.owner.day-review'))
        ->assertForbidden();
});
