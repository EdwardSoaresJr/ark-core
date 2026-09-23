<?php

use App\Ark\Operations\Documents\PdfRuntimePaths;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Spatie\Browsershot\Browsershot;

function seedFinancialSidebarLayout(): void
{
    test()->seed(ArkAuthorizationSeeder::class);
    test()->seed(RepairOrderStatusCatalogSeeder::class);
}

test('desktop financial sidebar stays 360px until 1440px then locks 380px on the review rail', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->not->toContain('--ops-estimate-rail-width: 320px;')
        ->not->toContain('--ops-estimate-rail-width: 340px;')
        ->toContain('max-width: min(100%, var(--ops-estimate-rail-width))')
        ->toContain('.ops-review-rail--pinned')
        ->toContain('position: sticky')
        ->toContain('var(--ops-ro-footer-offset')
        ->not->toContain('--ops-ro-rail-chrome-inset')
        ->toContain('overflow-y: auto');

    expect($css)->toMatch(
        '/:root \{\s*--ops-workspace-max: 1680px;\s*--ops-left-rail-width: 13rem;\s*--ops-estimate-rail-width: 360px;/'
    );

    expect($css)->toMatch(
        '/@media \(min-width: 1440px\) \{\s*:root \{\s*--ops-estimate-rail-width: 380px;/'
    );

    expect($css)->toMatch(
        '/@media \(min-width: 1024px\) \{\s*\.ops-estimate-layout \{\s*grid-template-columns: minmax\(0, 1fr\) var\(--ops-estimate-rail-width\);\s*\}\s*\.ops-review-rail \{\s*width: var\(--ops-estimate-rail-width\);\s*min-width: var\(--ops-estimate-rail-width\);\s*max-width: var\(--ops-estimate-rail-width\);/'
    );
});

test('uninvoiced estimate keeps financial summary and payment actions in the right rail', function () {
    seedFinancialSidebarLayout();
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('id="estimate-builder-rail"', false)
        ->assertSee('class="ops-review-rail ops-review-rail--pinned"', false)
        ->assertSee('id="estimate-total-panel"', false)
        ->assertSee('id="financial-payment-panel"', false)
        ->assertSee('Generate Final Invoice');
});

test('invoiced repair order keeps amount and method paired and date and reference full width', function () {
    seedFinancialSidebarLayout();
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('id="estimate-builder-rail"', false)
        ->assertSee('ops-rail-payment-fields__pair', false)
        ->assertSee('ops-rail-payment-fields__full', false)
        ->assertSee('name="amount"', false)
        ->assertSee('name="payment_method"', false)
        ->assertSee('name="paid_at"', false)
        ->assertSee('name="reference"', false)
        ->assertSee('Record Payment')
        ->assertDontSee('id="financial-rail"', false);

    $this->get(route('operations.repair-orders.workspace-tabs.show', [
        'repairOrder' => $repairOrder,
        'tab' => 'financial',
    ]))
        ->assertOk()
        ->assertSee('id="financial-rail"', false);
});

test('partially paid invoice still exposes rail payment controls', function () {
    seedFinancialSidebarLayout();
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        5000,
        PaymentMethod::Cash,
    );

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('id="estimate-builder-rail"', false)
        ->assertSee('Record Payment')
        ->assertSee('ops-rail-payment-fields__pair', false);
});

test('fully paid invoice keeps totals and balance in the right rail', function () {
    seedFinancialSidebarLayout();
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('id="estimate-builder-rail"', false)
        ->assertSee('id="estimate-total-panel"', false)
        ->assertSee('id="financial-payment-panel"', false);
});

test('review rail bounding rectangle is 380px at a 1600px CSS viewport', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $chrome = PdfRuntimePaths::resolveChromePath();
    $node = PdfRuntimePaths::resolveNodeBinary();
    $nodeModules = collect([
        base_path('node_modules'),
        getenv('PUPPETEER_NODE_MODULES') ?: null,
    ])->first(fn ($path) => is_string($path) && is_dir($path.DIRECTORY_SEPARATOR.'puppeteer'));

    if (
        ! is_string($chrome) || $chrome === '' || ! is_executable($chrome)
        || ! is_string($node) || $node === '' || ! is_executable($node)
        || ! is_string($nodeModules)
    ) {
        $this->markTestSkipped('Chromium, Node, and puppeteer are required for the rendered-width assertion.');
    }

    expect($css)->toMatch(
        '/@media \(min-width: 1440px\) \{\s*:root \{\s*--ops-estimate-rail-width: 380px;/'
    );
    expect($css)->toMatch(
        '/@media \(min-width: 1024px\) \{\s*\.ops-estimate-layout \{\s*grid-template-columns: minmax\(0, 1fr\) var\(--ops-estimate-rail-width\);/'
    );
    expect($css)->toContain('min-width: var(--ops-estimate-rail-width);');

    $layoutCss = <<<'CSS'
:root { --ops-estimate-rail-width: 360px; }
@media (min-width: 1440px) {
    :root { --ops-estimate-rail-width: 380px; }
}
.ops-estimate-layout {
    display: grid;
    align-items: start;
    gap: 0.5rem;
    grid-template-columns: minmax(0, 1fr);
    min-width: 0;
}
.ops-estimate-main { min-width: 0; }
.ops-review-rail {
    display: grid;
    gap: 0.375rem;
    width: 100%;
    min-width: 0;
    max-width: min(100%, var(--ops-estimate-rail-width));
    justify-self: end;
}
@media (min-width: 1024px) {
    .ops-estimate-layout {
        grid-template-columns: minmax(0, 1fr) var(--ops-estimate-rail-width);
    }
    .ops-review-rail {
        width: var(--ops-estimate-rail-width);
        min-width: var(--ops-estimate-rail-width);
        max-width: var(--ops-estimate-rail-width);
        justify-self: stretch;
    }
}
CSS;

    $html = <<<HTML
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>{$layoutCss}</style>
</head>
<body style="margin:0">
<div class="ops-estimate-layout">
    <div class="ops-estimate-main">estimate</div>
    <aside id="estimate-builder-rail" class="ops-review-rail ops-review-rail--pinned">rail</aside>
</div>
</body>
</html>
HTML;

    $browser = Browsershot::html($html)
        ->windowSize(1600, 1000)
        ->setChromePath($chrome)
        ->setNodeBinary($node)
        ->setNodeModulePath($nodeModules);

    if ($npm = PdfRuntimePaths::resolveNpmBinary($node)) {
        $browser->setNpmBinary($npm);
    }

    if (config('services.pdf.no_sandbox')) {
        $browser->noSandbox();
    }

    $payload = json_decode($browser->evaluate(
        'JSON.stringify({ width: document.querySelector("#estimate-builder-rail").getBoundingClientRect().width, viewport: window.innerWidth, token: getComputedStyle(document.documentElement).getPropertyValue("--ops-estimate-rail-width").trim() })'
    ), true);

    expect($payload)->toBeArray();
    expect($payload['viewport'])->toBe(1600);
    expect($payload['token'])->toBe('380px');
    expect($payload['width'])->toBeGreaterThanOrEqual(379.0);
    expect($payload['width'])->toBeLessThanOrEqual(381.0);
});
