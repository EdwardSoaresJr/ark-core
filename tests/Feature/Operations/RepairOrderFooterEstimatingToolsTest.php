<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Facades\Process;

require_once __DIR__.'/../../Support/HostedPartsTechHttp.php';

beforeEach(function (): void {
    bindHostedPartsTechHttpClient();

    config()->set('services.partstech.username', 'ark-shop');
    config()->set('services.partstech.password', 'shop-password');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    $this->actingAs(actingAsLearnCurrentAdvisor());
});

/**
 * @return array{0: RepairOrder}
 */
function footerToolsRepairOrder(): array
{
    $customer = Customer::query()->create(['first_name' => 'Synthetic', 'last_name' => 'Footer', 'phone' => '555-0144']);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2018, 'make' => 'Honda', 'model' => 'Accord']);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Footer tools',
    ]);

    foreach (['Front Brake Noise' => ConcernBillingPosture::Default, 'Warranty oil leak' => ConcernBillingPosture::Warranty] as $summary => $posture) {
        RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => $summary,
            'disposition' => RepairOrderConcernDisposition::Recommended,
            'billing_posture' => $posture,
            'position' => 0,
        ]);
    }

    return [$repairOrder];
}

test('the footer carries the estimating tools and the cart manager sits outside the estimate lines', function () {
    [$repairOrder] = footerToolsRepairOrder();

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent();
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $dock = $xpath->query('//*[@data-ro-orientation-header]')->item(0);

    expect($dock)->not->toBeNull()
        ->and($xpath->query('.//*[@data-ro-footer-tools]//*[@data-toolbar-group="parts-catalog"]', $dock)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-ro-footer-tools]//*[@data-toolbar-group="labor-guide"]', $dock)->length)->toBe(1)
        ->and($xpath->query('.//button[@data-ro-footer-cart]', $dock)->length)->toBe(1)
        ->and($xpath->query('.//button[@data-ro-footer-tools-toggle]', $dock)->length)->toBe(1)
        ->and($xpath->query('//*[@data-estimate-tools]//*[@data-toolbar-group]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-estimate-tools]//button[contains(normalize-space(), "Add Work")]')->length)->toBe(0)
        ->and($xpath->query('.//button[@data-workspace-modal-trigger="add-work"]', $dock)->length)->toBe(1)
        ->and($xpath->query('//button[@data-workspace-modal-trigger="add-work"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-partstech-cart-manager]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="estimate-lines"]//*[@data-partstech-cart-manager]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-partstech-cart-manager]//select[@data-cart-concern]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-partstech-cart-manager]//select[@data-cart-matrix]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-partstech-cart-manager]//*[@data-cart-duplicate-confirm]//input[@type="checkbox"][@data-cart-duplicate-ack]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-partstech-cart-manager]//*[@id="partstech-cart-issues"][@role="status"][@aria-live="polite"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-partstech-cart-manager]//button[@data-cart-import][@aria-describedby="partstech-cart-issues"]')->length)->toBe(1)
        ->and($xpath->query('.//*[@data-ro-dock-summary][contains(@class, "flex-nowrap")]', $dock)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-ro-dock-status][contains(@class, "d-none")][contains(@class, "d-sm-inline")]', $dock)->length)->toBe(1)
        ->and($xpath->query('.//button[@data-ro-footer-tools-toggle][@aria-label="Tools"]', $dock)->length)->toBe(1)
        ->and($xpath->query('.//button[@data-ro-footer-cart][@aria-label="PartsTech cart"]', $dock)->length)->toBe(1)
        ->and($html)->toContain('repairOrderId: '.$repairOrder->id.', concernId: preferredConcernId')
        ->and($html)->toContain('arkPartsTechCartManager(')
        ->and($html)->toContain("'ark:partstech-launched'")
        ->and($html)->not->toContain('Use concern default');
});

test('a closed repair order has no estimating tools or cart in its footer', function () {
    [$repairOrder] = footerToolsRepairOrder();
    $repairOrder->update(['status' => RepairOrderStatus::Closed]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-ro-footer', false)
        ->assertDontSee('data-ro-footer-tools', false)
        ->assertDontSee('data-ro-footer-cart', false)
        ->assertDontSee('data-partstech-cart-manager', false)
        ->assertDontSee('data-workspace-modal-trigger="add-work"', false)
        ->assertDontSee('+ Add Work', false);
});

test('on phones a line total that includes a shop fee and tax says so', function () {
    [$repairOrder] = footerToolsRepairOrder();
    $concern = $repairOrder->concerns()->where('summary', 'Front Brake Noise')->sole();
    ShopSettings::current()->update(['tax_enabled' => true, 'default_tax_rate' => '8.100', 'taxable_parts' => true, 'taxable_shop_fees' => true, 'shop_fee_enabled' => true, 'shop_fee_rate' => '5.000', 'shop_fee_cap_cents' => 3500]);

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part->value,
        'description' => '5W-30 oil (1 qt)',
        'quantity' => '5.00',
        'part_cost' => '6.40',
        'unit_price' => '12.16',
        'pricing_mode' => 'manual',
    ])->assertRedirect();

    $line = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->sole();

    expect($line->subtotal_cents)->toBe(6080)
        ->and($line->shop_fee_cents)->toBeGreaterThan(0)
        ->and($line->tax_cents)->toBeGreaterThan(0)
        ->and($line->total_cents)->toBe($line->subtotal_cents - $line->standing_discount_cents + $line->shop_fee_cents + $line->tax_cents);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent();
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    $breakdown = (new DOMXPath($dom))->query('//*[@data-line-total-breakdown]')->item(0);

    expect($breakdown)->not->toBeNull()
        ->and(preg_replace('/\s+/', ' ', trim($breakdown->textContent)))->toBe(sprintf(
            '%s + %s shop fee + %s tax',
            '$60.80',
            '$'.number_format($line->shop_fee_cents / 100, 2),
            '$'.number_format($line->tax_cents / 100, 2),
        ))
        ->and($breakdown->getAttribute('class'))->toContain('d-md-none');
});

test('the cart manager keeps concern, matrix and re-pull choices per part', function () {
    $result = Process::run([
        'node',
        '--test',
        base_path('tests/JavaScript/ark-partstech-cart-manager.test.mjs'),
    ]);

    if (! $result->successful()) {
        $this->fail($result->errorOutput()."\n".$result->output());
    }

    expect($result->exitCode())->toBe(0);
});
