<?php

use App\Ark\Operations\Workstations\WorkstationPresence;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

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

function tablerCssRule(string $css, string $selector): string
{
    $start = strpos($css, "\n".$selector." {");
    expect($start)->not->toBeFalse("tabler.css has no `{$selector}` rule");

    return substr($css, $start, strpos($css, '}', $start) - $start);
}

test('the top bar stays at the top of the viewport at one height, with the workspace strip and sticky panels below it', function (): void {
    $css = (string) file_get_contents(resource_path('css/tabler.css'));

    expect(tablerCssRule($css, ':root'))->toContain('--ark-topbar-height: 3.5rem;');

    $topbar = tablerCssRule($css, '.ark-topbar');
    expect($topbar)
        ->toContain('position: sticky;')
        ->toContain('top: 0;')
        ->toContain('z-index: 1022;')
        ->toContain('height: var(--ark-topbar-height);')
        // The station chooser and lock are fixed overlays inside the bar: nothing may contain them.
        ->not->toContain('transform')
        ->not->toContain('filter')
        ->not->toContain('min-height');

    expect(tablerCssRule($css, '.ark-workspace-tabs'))
        ->toContain('position: sticky;')
        ->toContain('top: var(--ark-topbar-height);')
        ->toContain('z-index: 1021;');

    expect(tablerCssRule($css, '.page'))
        ->toContain('--ops-sticky-stack-offset: calc(var(--ark-topbar-height) + var(--ops-workspace-tabs-height, 0px) + 1rem);');

    expect(tablerCssRule($css, '.ark-sticky-below-workspace-tabs'))
        ->toContain('top: calc(var(--ark-topbar-height) + var(--ops-workspace-tabs-height, 0px));');

    expect(tablerCssRule($css, 'html:has([data-ark-topbar])'))
        ->toContain('scroll-padding-top: calc(var(--ark-topbar-height) + var(--ops-workspace-tabs-height, 0px));');
});

test('every Tabler staff page has one top bar, followed by the workspace strip', function (string $route): void {
    $html = $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route($route))
        ->assertOk()
        ->getContent();
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $bar = $xpath->query('//header[@data-ark-topbar]');

    expect($bar->length)->toBe(1)
        ->and($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " ark-topbar ")]')->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ark-page-context"]', $bar->item(0))->length)->toBe(1)
        ->and($xpath->query('following-sibling::*[1][@id="ops-workspace-tabs"]', $bar->item(0))->length)->toBe(1);
})->with([
    'dashboard' => 'operations.index',
    'customers' => 'operations.customers.search',
    'repair orders' => 'operations.repair-orders.index',
]);
