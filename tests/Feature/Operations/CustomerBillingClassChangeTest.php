<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\WorkCompletionAuthorization;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    ShopSettings::current()->update([
        'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
        'labor_categories' => ShopSettings::DEFAULT_LABOR_CATEGORIES,
        'tax_enabled' => false,
        'shop_fee_enabled' => false,
    ]);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
});

/**
 * @return array{0: RepairOrder, 1: RepairOrderLine}
 */
function classChangeRepairOrder(Customer $customer, RepairOrderStatus $status, bool $approved = false): array
{
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2020, 'make' => 'Honda', 'model' => 'Pilot']);
    $repairOrder = RepairOrder::query()->create(['customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'status' => $status, 'concern_summary' => 'Synthetic class change']);
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Customer pay work',
        'disposition' => $approved ? RepairOrderConcernDisposition::Approved : RepairOrderConcernDisposition::Recommended,
        'position' => 1,
        'billing_posture' => 'customer_pay',
    ]);
    $line = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => 'labor',
        'description' => 'Labor',
        'quantity' => '2.00',
        'unit_price_cents' => 16500,
        'labor_rate_cents' => 16500,
    ]);
    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    if ($approved) {
        approveCurrentAmounts($repairOrder);
    }

    return [$repairOrder->fresh(), $line->fresh()];
}

function classChangeCustomer(string $type): Customer
{
    return Customer::query()->create(['first_name' => 'Synthetic', 'last_name' => 'Classchange', 'phone' => '555-0181', 'customer_type' => $type]);
}

function classChangeTo(Customer $customer, string $type, RepairOrder $from): void
{
    test()->patch(route('operations.customers.update', $customer), [
        'first_name' => $customer->first_name,
        'last_name' => $customer->last_name,
        'customer_type' => $type,
        'repair_order_id' => $from->repair_order_id,
    ])->assertSessionHasNoErrors();
}

function classChangeEvents(RepairOrder $repairOrder): array
{
    return OperationalEvent::query()
        ->where('event_name', OperationalEventName::CustomerBillingClassChanged->value)
        ->where('aggregate_id', $repairOrder->id)
        ->pluck('payload_json')
        ->all();
}

test('a billing class change records its effect on each open repair order and updates their invoices', function () {
    $customer = classChangeCustomer('Retail');
    [$open] = classChangeRepairOrder($customer, RepairOrderStatus::Estimate);
    [$invoiced] = classChangeRepairOrder($customer, RepairOrderStatus::ReadyPickup, approved: true);
    [$closed, $closedLine] = classChangeRepairOrder($customer, RepairOrderStatus::Closed);
    issueFinalInvoiceFor($invoiced);
    $balances = app(BalanceDueCalculator::class);
    $balances->issuedInvoice($invoiced->fresh())->markPresentedToCustomer();
    app(RecordLedgerEntryAction::class)->recordPayment($invoiced->fresh(), 10000, PaymentMethod::Card);
    $versions = [$open->id => (int) $open->fresh()->estimate_version, $invoiced->id => (int) $invoiced->fresh()->estimate_version];
    $closedLineBefore = $closedLine->fresh()->getAttributes();

    classChangeTo($customer, 'Military', $open);

    expect(classChangeEvents($open))->toBe([[
        'customer_id' => $customer->id,
        'prior_billing_class' => 'Retail',
        'new_billing_class' => 'Military',
        'prior_total_cents' => 33000,
        'new_total_cents' => 29700,
    ]])
        ->and(classChangeEvents($invoiced))->toHaveCount(1)
        ->and(classChangeEvents($closed))->toBe([])
        ->and((int) $open->fresh()->estimate_version)->toBeGreaterThan($versions[$open->id])
        ->and((int) $invoiced->fresh()->estimate_version)->toBeGreaterThan($versions[$invoiced->id])
        ->and($closedLine->fresh()->getAttributes())->toBe($closedLineBefore);

    $balance = $balances->forRepairOrder($invoiced->fresh());
    $invoice = $balances->issuedInvoice($invoiced->fresh());

    expect($balance->invoiceTotalCents)->toBe(29700)
        ->and($balance->paymentsAppliedCents)->toBe(10000)
        ->and($balance->balanceDueCents)->toBe(19700)
        ->and($invoice->priorRevisions()[0])->toMatchArray(['revision' => 1, 'trigger' => 'customer_billing_class_changed', 'invoice_total_cents' => 33000]);
});

test('a billing class change that raises approved work waits for reauthorization', function () {
    $customer = classChangeCustomer('Military');
    [$invoiced, $line] = classChangeRepairOrder($customer, RepairOrderStatus::ReadyPickup, approved: true);
    issueFinalInvoiceFor($invoiced);

    classChangeTo($customer, 'Retail', $invoiced);

    $repairOrder = $invoiced->fresh(['concerns', 'lines.concern']);

    expect(classChangeEvents($invoiced)[0])->toMatchArray(['prior_total_cents' => 29700, 'new_total_cents' => 33000])
        ->and(app(BalanceDueCalculator::class)->forRepairOrder($repairOrder)->invoiceTotalCents)->toBe(29700)
        ->and(app(WorkCompletionAuthorization::class)->linesRequiringAuthorization($repairOrder)->pluck('id')->all())->toBe([$line->id]);
});

test('a billing class change that moves no totals leaves repair orders alone', function () {
    $customer = classChangeCustomer('Retail');
    [$open] = classChangeRepairOrder($customer, RepairOrderStatus::Estimate);
    $version = (int) $open->fresh()->estimate_version;

    classChangeTo($customer, 'Business', $open);

    expect(classChangeEvents($open))->toBe([])
        ->and((int) $open->fresh()->estimate_version)->toBe($version);
});
