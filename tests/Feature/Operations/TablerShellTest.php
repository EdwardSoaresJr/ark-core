<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\View\ViewException;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);

    $this->app->instance(Vite::class, new class extends Vite
    {
        public function __invoke($entrypoints, $buildDirectory = null)
        {
            return new HtmlString('<!-- vite: '.implode(',', (array) $entrypoints).' -->');
        }
    });
});

/**
 * @return list<string>
 */
function primaryNavHrefs(string $html, string $start, string $end): array
{
    $from = strpos($html, $start);
    expect($from)->not->toBeFalse();
    $section = substr($html, $from, strpos($html, $end, $from) - $from);
    preg_match_all('/href="([^"]+)"/', $section, $matches);

    return array_values(array_filter(
        $matches[1],
        fn (string $href): bool => $href !== '#',
    ));
}

test('customer search renders in the Tabler shell after the legacy stylesheet', function (): void {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->assertSee('<!-- vite: resources/css/app.css,resources/css/tabler.css,resources/js/app.js,resources/js/tabler.js -->', false)
        ->assertSee('navbar navbar-vertical', false)
        ->assertSee('id="ark-sidebar-menu"', false)
        ->assertSee('class="page-title">Customers</h2>', false)
        ->assertDontSee('data-ops-mobile-nav', false)
        ->assertDontSee('class="ops-left-rail"', false);
});

test('unmigrated views keep the legacy shell and never load Tabler', function (): void {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->followingRedirects()->get(route('operations.learn.index'))
        ->assertOk()
        ->assertSee('<!-- vite: resources/css/app.css,resources/js/app.js -->', false)
        ->assertSee('data-ops-mobile-nav', false)
        ->assertSee('ops-rail-nav', false)
        ->assertDontSee('tabler', false)
        ->assertDontSee('navbar-vertical', false);
});

test('the Tabler sidebar lists the same destinations as the legacy rail', function (User $user): void {
    $legacy = $this->actingAs($user)->followingRedirects()->get(route('operations.learn.index'))->assertOk()->getContent();
    $tabler = $this->actingAs($user)->get(route('operations.customers.search'))->assertOk()->getContent();

    $legacyHrefs = primaryNavHrefs($legacy, '<nav aria-label="Primary operations" class="ops-rail-nav">', '</nav>');
    $tablerHrefs = primaryNavHrefs($tabler, 'id="ark-sidebar-menu"', '</ul>');

    expect($tablerHrefs)->not->toBeEmpty()
        ->and($tablerHrefs)->toBe($legacyHrefs);
})->with([
    'admin' => fn () => User::factory()->create()->assignRole(ArkRole::Admin->value),
    'advisor' => fn () => User::factory()->create()->assignRole(ArkRole::Advisor->value),
    'advisor with settings and records' => fn () => User::factory()->create()
        ->assignRole(ArkRole::Advisor->value)
        ->givePermissionTo('settings.manage', 'customers.manage', 'repair_orders.view', 'financial.view'),
]);

test('the Tabler sidebar keeps the live communications count hook', function (): void {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->assertSee('data-ops-comms-nav-link', false)
        ->assertSee('data-ops-comms-nav-style="tabler"', false);
});

test('the top bar has one Inbox menu with every channel destination instead of a tab strip', function (): void {
    $html = $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->assertSee('aria-label="Inbox, 0 need attention"', false)
        ->assertDontSee('ops-comms-channel-strip', false)
        ->assertDontSee('flex-fill overflow-x-auto d-none d-lg-block', false)
        ->getContent();

    expect(substr_count($html, 'data-ark-channel-strip'))->toBe(1)
        ->and(substr_count($html, 'data-ark-inbox-menu'))->toBe(1);

    $menuHrefs = primaryNavHrefs($html, 'data-ark-channel-strip', '</nav>');
    $expected = array_map(
        fn (string $channel): string => url('/app/communications/inbox').'?filter=needs'.($channel === '' ? '' : '&amp;channel='.$channel),
        ['', 'phone', 'sms', 'messenger', 'email', 'portal'],
    );

    expect($menuHrefs)->toBe($expected);
});

test('customer filters stay collapsed on phones until one is active', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.customers.search', ['q' => 'Rosa']))
        ->assertOk()
        ->assertSee('class="col-12 col-lg-6 col-xxl-auto collapse d-md-block" id="customer-filters"', false)
        ->assertDontSee('aria-expanded="true"', false);

    $this->actingAs($advisor)
        ->get(route('operations.customers.search', ['created_from' => '2026-01-01']))
        ->assertOk()
        ->assertSee('class="col-12 col-lg-6 col-xxl-auto collapse d-md-block show" id="customer-filters"', false)
        ->assertSee('aria-expanded="true"', false);
});

test('customer search uses the fluid workspace and the record card grid', function (): void {
    Customer::query()->create(['first_name' => 'Grid', 'last_name' => 'Card', 'phone' => '555-0199']);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->assertSee('<div class="ark-card-grid">', false)
        ->assertSee('Grid Card')
        ->assertDontSee('container-xl', false)
        ->assertDontSee('row-cards', false);
});

test('each workspace layout renders its Tabler container, with no title row of its own', function (string $layout, array $expected): void {
    $html = (string) $this->blade(
        '<x-tabler.page :layout="$layout" title="Workspace">Body</x-tabler.page>',
        ['layout' => $layout],
    );

    expect($html)->toContain('Body')
        ->not->toContain('page-header')
        ->not->toContain('page-title');

    foreach ($expected as $fragment) {
        expect($html)->toContain($fragment);
    }
})->with([
    'fluid' => ['fluid', ['<div class="page-body">', '<div class="container-fluid">']],
    'focused' => ['focused', ['<div class="page-body">', '<div class="container-narrow">']],
    'split' => ['split', ['<div class="page-body">', '<div class="container-fluid d-flex flex-column flex-fill">']],
    'canvas' => ['canvas', ['<div class="page-body mb-0">', '<div class="container-fluid d-flex flex-column flex-fill px-0">']],
]);

test('a page must name a known workspace layout', function (): void {
    $this->blade('<x-tabler.page layout="wide">Body</x-tabler.page>');
})->throws(ViewException::class, 'Unknown Tabler page layout [wide].');

test('the top bar carries the page title, its breadcrumb and its actions, ahead of the global controls', function (): void {
    $html = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->getContent();
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $bar = $xpath->query('//header[@data-ark-topbar]')->item(0);
    $context = $xpath->query('.//*[@id="ark-page-context"]', $bar)->item(0);

    expect($bar)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " page-header ")]')->length)->toBe(0)
        ->and($xpath->query('//h2[contains(concat(" ", normalize-space(@class), " "), " page-title ")]')->length)->toBe(1)
        ->and(trim($xpath->query('.//h2[contains(concat(" ", normalize-space(@class), " "), " page-title ")]', $context)->item(0)->textContent))->toBe('Customers')
        ->and(trim($xpath->query('.//ol[@aria-label="Breadcrumbs"]/li', $context)->item(0)->textContent))->toBe('Records')
        ->and($xpath->query('//main//*[@id="ark-page-context"]')->length)->toBe(0)
        // The page's context comes before the global controls (the account menu) in the same bar.
        ->and($xpath->query('.//*[@id="ark-page-context"]/following-sibling::*[contains(concat(" ", normalize-space(@class), " "), " navbar-nav ")]', $bar)->length)->toBe(1);
});

test('secondary actions are one labeled element each, marked for the overflow menu on phones', function (): void {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->assertSee('data-ark-page-action="secondary" aria-label="Vehicles"', false)
        ->assertSee('data-ark-page-action="secondary" aria-label="Job Board"', false)
        ->assertSee('data-ark-page-overflow hidden', false)
        ->assertSee('aria-label="More actions"', false)
        ->assertDontSee('btn-icon d-sm-none', false);
});

test('the Tabler account menu keeps profile, theme, and sign out', function (): void {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->assertSee('data-bs-toggle="dropdown"', false)
        ->assertSee(route('profile.edit'), false)
        ->assertSee('window.arkToggleTheme()', false)
        ->assertSee('action="'.route('logout').'"', false);
});

test('check in is offered only to staff who can manage repair orders', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('operations.access', 'customers.manage');

    expect($viewer->can(ArkCapability::RepairOrdersManage->value))->toBeFalse();

    $this->actingAs($viewer)
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->assertDontSee('href="'.route('operations.intake.create').'"', false);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->assertSee('href="'.route('operations.intake.create').'"', false);
});

test('customer search uses Tabler pagination and keeps the filters in page links', function (): void {
    foreach (range(1, 25) as $index) {
        Customer::query()->create([
            'first_name' => 'Page',
            'last_name' => sprintf('Person %02d', $index),
            'phone' => '555-7'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
        ]);
    }

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search', ['q' => 'Page']))
        ->assertOk()
        ->assertSee('class="pagination"', false)
        ->assertSee('page-link', false)
        ->assertSee('q=Page&amp;page=2', false)
        ->assertSee('25 total');
});

test('customer search shows a Tabler empty state with a way back', function (): void {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search', ['q' => 'Nobody Here']))
        ->assertOk()
        ->assertSee('class="empty"', false)
        ->assertSee('No customers match &ldquo;Nobody Here&rdquo;.', false)
        ->assertSee('Clear search');
});
