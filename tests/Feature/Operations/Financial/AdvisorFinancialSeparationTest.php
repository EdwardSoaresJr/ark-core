<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RepairOrderFinancialPresenter;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\ApprovalForecastProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

/**
 * Acceptance standard for repair-order money labels.
 *
 * Approval, collection, and amount due stay separate.
 * Awaiting customer approval, authorized work, collected payments, and
 * currently due are four figures. The full estimate total is not labeled
 * Approved. Voided payments stay out of collected. Reading the page does
 * not write the ledger.
 *
 * The fixture matches the rehearsal shape: recommended work of $893.67,
 * authorized work of $0.00, a paid invoice snapshot of $0.00, an active
 * payment of $4.00, and a voided payment of $151.00.
 */
beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('unapproved work is not labeled as authorized beside collected payments and amount due', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = advisorFinancialSeparationRepairOrder();
    $ledgerRows = RepairOrderLedgerEntry::query()->count();
    $forecast = app(ApprovalForecastProjection::class)->for($repairOrder);
    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder);
    $presented = app(RepairOrderFinancialPresenter::class)->for($repairOrder, $totals);

    expect($forecast['pending_cents'])->toBe(89367)
        ->and($forecast['approved_cents'])->toBe(0)
        ->and($presented['paymentsAppliedCents'])->toBe(400)
        ->and($presented['oweTodayCents'])->toBe(0)
        ->and($presented['approvedWorkTotalCents'])->toBe(0)
        ->and(RepairOrderLedgerEntry::query()->count())->toBe($ledgerRows);
});

test('the repair order page does not call the full estimate approved', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = advisorFinancialSeparationRepairOrder();
    $ledgerRows = RepairOrderLedgerEntry::query()->count();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('$893.67')
        ->assertSee('$4.00')
        ->assertSee('$0.00')
        ->assertDontSee('Approved $893.67');

    expect(RepairOrderLedgerEntry::query()->count())->toBe($ledgerRows);
});

function advisorFinancialSeparationRepairOrder(): RepairOrder
{
    ShopSettings::current()->update(['shop_fee_enabled' => false, 'tax_enabled' => false]);

    $customer = Customer::query()->create([
        'first_name' => 'Edward',
        'last_name' => 'Soares',
        'phone' => '7195551711',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'Ram',
        'model' => '2500',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Noise and vibration from the brakes.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake work awaiting approval',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake service',
        'quantity' => '1.00',
        'unit_price_cents' => 89367,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());
    $repairOrder = $repairOrder->fresh();

    $invoice = EstimateDocument::query()->create([
        'repair_order_id' => $repairOrder->id,
        'document_type' => FinancialDocumentType::Invoice,
        'document_number' => 1711,
        'snapshot_json' => ['totals' => ['total_cents' => 0]],
        'status' => InvoiceStatus::Paid,
        'generated_at' => now(),
        'issued_at' => now(),
    ]);

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $customer->id,
        'financial_document_id' => $invoice->id,
        'entry_type' => LedgerEntryType::Payment,
        'payment_method' => PaymentMethod::Cash,
        'amount_cents' => 400,
        'recorded_at' => now()->subDay(),
    ]);

    RepairOrderLedgerEntry::query()->create([
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $customer->id,
        'financial_document_id' => $invoice->id,
        'entry_type' => LedgerEntryType::Payment,
        'payment_method' => PaymentMethod::Cash,
        'amount_cents' => 15100,
        'recorded_at' => now()->subDays(2),
        'voided_at' => now()->subDay(),
    ]);

    return $repairOrder->fresh();
}
