<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
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
        ->and($xpath->query('//*[@data-partstech-cart-manager]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="estimate-lines"]//*[@data-partstech-cart-manager]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-partstech-cart-manager]//select[@data-cart-concern]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-partstech-cart-manager]//select[@data-cart-matrix]')->length)->toBe(1)
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
        ->assertDontSee('data-partstech-cart-manager', false);
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
