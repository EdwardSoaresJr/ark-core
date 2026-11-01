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
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('<!-- vite: resources/css/app.css,resources/js/app.js -->', false)
        ->assertSee('data-ops-mobile-nav', false)
        ->assertSee('ops-rail-nav', false)
        ->assertDontSee('tabler', false)
        ->assertDontSee('navbar-vertical', false);
});

test('the Tabler sidebar lists the same destinations as the legacy rail', function (User $user): void {
    $legacy = $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();
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

test('below desktop width the channel strip becomes one menu with the same destinations', function (): void {
    $html = $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->assertSee('class="ark-legacy flex-fill overflow-auto d-none d-lg-block"', false)
        ->assertSee('aria-label="Communications, 0 need attention"', false)
        ->getContent();

    $menuHrefs = primaryNavHrefs($html, '<span class="dropdown-header">Communications</span>', '</div>');
    $stripHrefs = primaryNavHrefs($html, 'class="ops-comms-channel-strip-metrics"', '</section>');

    expect($menuHrefs)->not->toBeEmpty()
        ->and($menuHrefs)->toBe($stripHrefs);
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

test('each workspace layout renders its Tabler container', function (string $layout, array $expected): void {
    $html = (string) $this->blade(
        '<x-tabler.page :layout="$layout" title="Workspace">Body</x-tabler.page>',
        ['layout' => $layout],
    );

    expect($html)->toContain('class="page-title">Workspace</h2>')->toContain('Body');

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

test('icon-only header actions keep an accessible name and a tooltip', function (): void {
    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.customers.search'))
        ->assertOk()
        ->assertSee('class="btn btn-icon d-sm-none" title="Vehicles" aria-label="Vehicles" data-bs-toggle="tooltip"', false)
        ->assertSee('class="btn btn-icon d-sm-none" title="Workboard" aria-label="Workboard" data-bs-toggle="tooltip"', false);
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
