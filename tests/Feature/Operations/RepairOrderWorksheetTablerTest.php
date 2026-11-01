<?php

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\ScopeEntryKind;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Database\Seeders\ShopSettingsSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->seed(ShopSettingsSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
});

function worksheetTablerXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

function worksheetTablerHasClass(string $class): string
{
    return 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
}

function worksheetTablerOuterHtml(DOMXPath $xpath, string $id): ?string
{
    $node = $xpath->query('//*[@id="'.$id.'"]')->item(0);

    if (! $node instanceof DOMElement) {
        return null;
    }

    // The note visibility chooser names its inputs with uniqid(), which differs on every render.
    return preg_replace('/note-audience-[0-9a-f]+/', 'note-audience-*', $node->ownerDocument->saveHTML($node));
}

/**
 * @return array{0: User, 1: RepairOrder, 2: RepairOrderConcern, 3: RepairOrderLine}
 */
function worksheetTablerFixture(): array
{
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForEstimateWorkspace();

    test()->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
            'summary' => 'Front brakes grinding',
            'observed_summary' => 'Front brakes grinding',
        ])
        ->assertRedirect();

    $concern = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->latest('id')->firstOrFail();

    test()->actingAs($advisor)
        ->post(route('operations.repair-orders.lines.store', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Replace front pads',
            'quantity' => '1.50',
            'labor_entered_hours' => '1.50',
            'unit_price' => '165.00',
        ])
        ->assertRedirect();

    $line = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->firstOrFail();

    return [$advisor, $repairOrder->fresh(), $concern, $line];
}

test('the worksheet renders on Tabler with no legacy island of its own', function (): void {
    [$advisor, $repairOrder] = worksheetTablerFixture();

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();
    $xpath = worksheetTablerXpath($html);
    $root = $xpath->query('//*[@data-worksheet-root]')->item(0);

    // Left in place: the lazily loaded workspace tab panels, which another slice moves with the rail tabs.
    $islands = $xpath->query('.//*['.worksheetTablerHasClass('ark-legacy').'][not(.//*[@data-workspace-tab-panel])]', $root);

    expect($root)->toBeInstanceOf(DOMElement::class)
        ->and($islands->length)->toBe(0)
        ->and($xpath->query('.//*['.worksheetTablerHasClass('ark-worksheet-island').']//*[@data-workspace-tab-panel]', $root)->length)->toBeGreaterThan(0)
        ->and(substr_count($html, 'ops-estimate-workspace--builder'))->toBe($xpath->query('.//*[@data-workspace-tab-panel]', $root)->length)
        ->and($html)->not->toContain('ops-worksheet-busy-overlay')
        ->and($html)->not->toContain('ops-service-lane-band')
        ->and($html)->not->toContain('ops-ro-orientation-header')
        ->and($html)->not->toContain('ops-ro-footer')
        ->and($html)->not->toContain('ops-inspect-popover')
        ->and($html)->not->toContain('ops-help-tip')
        ->and($html)->not->toContain('ops-mileage-inline')
        ->and($html)->not->toContain('ops-worksheet-saving')
        ->and($html)->not->toContain('ops-review-action--labor-guide');
});

test('the worksheet keeps the ids, hooks, and form routes its scripts and saves depend on', function (): void {
    [$advisor, $repairOrder, $concern, $line] = worksheetTablerFixture();

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();
    $xpath = worksheetTablerXpath($html);
    $root = $xpath->query('//*[@data-worksheet-root]')->item(0);
    $dock = $xpath->query('//*[@id="ro-orientation-header"]')->item(0);

    expect($root->getAttribute('x-data'))->toContain('arkWorksheetContinuity')
        ->and($root->getAttribute('x-data'))->toContain("'ro-identity-band'")
        ->and($root->getAttribute('x-data'))->toContain("'ro-orientation-header'")
        ->and($xpath->query('.//*[@id="worksheet-status-flash"]', $root)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-worksheet-busy-overlay][@x-show="worksheetSaving"]', $root)->length)->toBe(1)
        ->and($xpath->query('.//*[@x-show="presenceMessage"]', $root)->length)->toBe(1)
        ->and($xpath->query('.//*[@x-show="versionDriftNotice"]', $root)->length)->toBe(1)
        ->and($xpath->query('.//*[@x-show="staleNotice"]', $root)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ro-identity-band"]['.worksheetTablerHasClass('card').']', $root)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ro-identity-band"]//*[@data-identity-present="customer"]', $root)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ro-identity-band"]//*[@data-identity-present="vehicle"]', $root)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ro-identity-band"]//*[@data-identity-present="visit"]', $root)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-mileage-inline][contains(@x-data, "arkRepairOrderMileage")]', $root)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="estimate-lines"][contains(@*[name()=":class"], "worksheetBusyPending")]', $root)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="worksheet-concerns"]/section[@id="concern-'.$concern->id.'"]', $root)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="line-'.$line->id.'"][@data-continuity-region="line"]', $root)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="estimate-builder-rail"]//*[@id="estimate-total-panel"]', $root)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="workspace-modal-host"]', $root)->length)->toBe(1)
        ->and($xpath->query('.//form[@id="concern-store-form"][@action="'.route('operations.repair-orders.concerns.store', $repairOrder).'"][@data-worksheet-continuity]', $root)->length)->toBe(1)
        ->and($dock)->toBeInstanceOf(DOMElement::class)
        ->and($dock->hasAttribute('data-ro-orientation-header'))->toBeTrue()
        ->and($dock->getAttribute('class'))->toContain('sticky-bottom')
        ->and($xpath->query('ancestor::*[@data-worksheet-root]', $dock)->length)->toBe(1)
        ->and($xpath->query('.//footer[@data-ro-footer][@x-data]', $dock)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-ro-dock-status]', $dock)->length)->toBe(1)
        ->and($xpath->query('.//button[@data-ro-footer-top][@aria-label="Back to top"]', $dock)->length)->toBe(1)
        ->and($xpath->query('//*['.worksheetTablerHasClass('ark-legacy').']//*[@id="ro-orientation-header"]')->length)->toBe(0);
});

test('the engine oil and testing package panels keep their forms and routes', function (): void {
    [, $repairOrder] = worksheetTablerFixture();

    $oil = view('operations.repair-orders.partials.repair-order-engine-oil-panel', [
        'repairOrder' => $repairOrder,
        'engineOilServices' => collect(),
        'estimateVersion' => 7,
    ])->render();
    $testing = view('operations.repair-orders.partials.repair-order-testing-package-panel', [
        'repairOrder' => $repairOrder,
        'testingAuthorizations' => collect(),
        'estimateVersion' => 7,
        'isTerminal' => false,
    ])->render();

    $oilXpath = worksheetTablerXpath($oil);
    $testingXpath = worksheetTablerXpath($testing);

    expect($oilXpath->query('//section['.worksheetTablerHasClass('card').'][@data-engine-oil-panel]//form[@method="POST"][@action="'.route('operations.repair-orders.maintenance.engine-oil.store', $repairOrder).'"][@data-refresh-scope="worksheet"]//input[@type="hidden"][@value="7"]')->length)->toBe(1)
        ->and($testingXpath->query('//section[@id="work-authorization"]['.worksheetTablerHasClass('card').']//form[@action="'.route('operations.repair-orders.work-authorization.testing.store', $repairOrder).'"]')->length)->toBe(1)
        ->and($testing)->toContain('No Testing Package authorized on this repair order yet.')
        ->and($oil.$testing)->not->toContain('slate-');
});

test('the full-page refresh after a line save returns the same line and scope markup as the page', function (): void {
    [$advisor, $repairOrder, $concern, $line] = worksheetTablerFixture();

    $saved = $this->actingAs($advisor)
        ->followingRedirects()
        ->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Replace front pads',
            'quantity' => '1.50',
            'labor_entered_hours' => '1.50',
            'unit_price' => '165.00',
        ])
        ->assertOk()
        ->getContent();

    $page = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();

    $refresh = $this->actingAs($advisor)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html'])
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();

    $savedXpath = worksheetTablerXpath($saved);
    $pageXpath = worksheetTablerXpath($page);
    $refreshXpath = worksheetTablerXpath($refresh);

    foreach (['line-'.$line->id, 'concern-'.$concern->id, 'concern-lines-'.$concern->id, 'concern-money-'.$concern->id, 'ro-identity-band', 'ro-orientation-header', 'estimate-total-panel', 'review-toolbar'] as $id) {
        $expected = worksheetTablerOuterHtml($pageXpath, $id);

        expect($expected)->not->toBeNull()
            ->and(worksheetTablerOuterHtml($refreshXpath, $id))->toBe($expected)
            ->and(worksheetTablerOuterHtml($savedXpath, $id))->toBe($expected);
    }
});

test('the line editor the worksheet fetches is Tabler markup with the same form as the page', function (): void {
    [$advisor, $repairOrder, , $line] = worksheetTablerFixture();
    $url = route('operations.repair-orders.show', ['repairOrder' => $repairOrder, 'editing_line' => $line->id]);

    $page = $this->actingAs($advisor)->get($url)->assertOk()->getContent();
    $fetched = $this->actingAs($advisor)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html'])
        ->get($url)
        ->assertOk()
        ->getContent();

    $pageXpath = worksheetTablerXpath($page);
    $fetchedXpath = worksheetTablerXpath($fetched);
    $editor = $fetchedXpath->query('//*[@id="worksheet-line-editor"]')->item(0);

    expect($editor)->toBeInstanceOf(DOMElement::class)
        ->and($fetchedXpath->query('.//form[@id="line-update-'.$line->id.'"][@action="'.route('operations.repair-orders.lines.update', [$repairOrder, $line]).'"][@data-worksheet-continuity]', $editor)->length)->toBe(1)
        ->and($fetchedXpath->query('.//form[@id="line-update-'.$line->id.'"]//input[@name="labor_entered_hours"]['.worksheetTablerHasClass('form-control').']', $editor)->length)->toBe(1)
        ->and($fetchedXpath->query('.//form[@id="line-update-'.$line->id.'"]//select[@name="labor_category_key"]['.worksheetTablerHasClass('form-select').']', $editor)->length)->toBe(1)
        ->and($fetchedXpath->query('.//*[contains(@class, "ops-field")]', $editor)->length)->toBe(0)
        ->and(worksheetTablerOuterHtml($fetchedXpath, 'worksheet-line-editor'))->toBe(worksheetTablerOuterHtml($pageXpath, 'worksheet-line-editor'));
});
