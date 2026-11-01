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
