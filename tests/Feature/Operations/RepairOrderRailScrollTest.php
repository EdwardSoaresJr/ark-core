<?php

use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\RefreshCustomerInvoiceAction;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
});

function railCss(): string
{
    return (string) file_get_contents(resource_path('css/tabler.css'));
}

function railSummary(string $html): string
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $summary = (new DOMXPath($document))->query('//*[@id="estimate-builder-rail"]//*[@data-rail-summary]')->item(0);

    expect($summary)->toBeInstanceOf(DOMElement::class);

    return trim((string) preg_replace('/\s+/', ' ', $summary->textContent));
}

test('a rail taller than the space above the dock scrolls on its own from lg, with its tabs and summary pinned', function (): void {
    $css = railCss();
    $media = substr($css, strpos($css, ".ops-rail-summary {\n    display: none;\n}"));
    $media = substr($media, 0, strpos($media, '/* A page\'s own sticky toolbar'));

    expect($media)
        ->toContain("@media (min-width: 1024px) {\n    .ops-review-rail--pinned.ops-review-rail--scrolls {")
        ->toContain('max-height: var(--ops-rail-max-height);')
        ->toContain('overflow-y: auto;')
        ->toContain('overflow-x: hidden;')
        ->toContain('scroll-padding-top: var(--ops-rail-pinned-height, 0px);')
        ->toContain(".ops-review-rail--scrolls #estimate-total-panel > .card-header {\n        top: 0;")
        ->toContain('top: var(--ops-rail-header-height, 0px);')
        // Scrolling carries on to the page at either end.
        ->not->toContain('overscroll-behavior');
});

test('the fit script only marks a desktop rail that does not fit, and watches what bounds its space', function (): void {
    $html = $this->get(route('operations.repair-orders.show', financialCloseoutRepairOrder()))->assertOk()->getContent();

    expect($html)
        ->toContain("rail.classList.toggle('ops-review-rail--scrolls', scrolls);")
        ->toContain("if (window.innerWidth < 1024) {\n                        rail.classList.remove('ops-review-rail--scrolls');")
        ->toContain("rail.style.setProperty('--ops-rail-max-height', available + 'px');")
        ->toContain("document.documentElement.style.setProperty('--ops-ro-dock-reserve', dockReserve);")
        ->toContain("document.body.style.setProperty('--ops-ro-dock-reserve', dockReserve);")
        ->toContain("document.querySelectorAll('#ops-workspace-tabs, [data-ro-orientation-header]')")
        ->not->toContain('rail.style.top =');
});

test('the closeout summary shows the estimate total and what is owed before an invoice', function (): void {
    $html = $this->get(route('operations.repair-orders.show', financialCloseoutRepairOrder()))->assertOk()->getContent();

    expect(railSummary($html))->toStartWith('Estimate total $')->toContain('Owe today $');
});

test('the closeout summary shows the invoice total and settlement balance once invoiced', function (): void {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))->assertOk()->getContent();

    expect(railSummary($html))->toStartWith('Invoice total $')->toContain('Settlement balance $');
});

test('the closeout summary shows the credit due when a paid invoice is revised below what was paid', function (): void {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder->fresh());

    $line = $repairOrder->lines()->firstOrFail();
    $line->forceFill(['unit_price_cents' => intdiv((int) $line->unit_price_cents, 2)])->save();
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());
    app(RefreshCustomerInvoiceAction::class)->executeIfNeeded($repairOrder->fresh(), auth()->user(), 'line_updated');

    expect(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder->fresh())->overpaidCents)->toBeGreaterThan(0);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))->assertOk()->getContent();

    expect(railSummary($html))->toStartWith('Invoice total $')->toContain('Credit due $')->not->toContain('Settlement balance');
});
