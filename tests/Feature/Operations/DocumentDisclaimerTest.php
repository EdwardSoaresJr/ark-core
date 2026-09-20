<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\DocumentDisclaimerComposer;
use App\Ark\Operations\Documents\DocumentPdfPresenter;
use App\Ark\Operations\Documents\EstimateSnapshotBuilder;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
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

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('estimate snapshot copies global and customer type disclaimers into documents block', function () {
    ShopSettings::current()->update([
        'estimate_disclaimer' => 'Global estimate disclaimer at snapshot time.',
        'authorization_language' => 'Customer authorizes listed repairs.',
        'customer_type_disclaimers' => [
            'fleet' => 'Fleet authorization required before work begins.',
        ],
    ]);

    $repairOrder = repairOrderForDocumentDisclaimer('Fleet');

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder);

    expect($snapshot['documents']['global_disclaimer'])->toBe('Global estimate disclaimer at snapshot time.')
        ->and($snapshot['documents']['customer_type'])->toBe('Fleet')
        ->and($snapshot['documents']['customer_type_disclaimer'])->toBe('Fleet authorization required before work begins.')
        ->and($snapshot['documents']['authorization_language'])->toBe('Customer authorizes listed repairs.');
});

test('invoice snapshot keeps disclaimer text after shop settings change', function () {
    ShopSettings::current()->update([
        'invoice_disclaimer' => 'Original invoice disclaimer.',
        'authorization_language' => 'Original authorization language.',
    ]);

    $repairOrder = repairOrderForDocumentDisclaimer('Retail');
    $repairOrder->update(['status' => RepairOrderStatus::ReadyPickup]);

    $invoice = app(GenerateInvoiceSnapshotAction::class)->execute($repairOrder->fresh());

    ShopSettings::current()->update([
        'invoice_disclaimer' => 'Changed invoice disclaimer.',
        'authorization_language' => 'Changed authorization language.',
    ]);

    $presented = app(DocumentPdfPresenter::class)->prepare($invoice->snapshot_json);

    expect($presented['documents']['global_disclaimer'])->toBe('Original invoice disclaimer.')
        ->and($presented['documents']['authorization_language'])->toBe('Original authorization language.');
});

test('document disclaimer composer resolves invoice global disclaimer from stored documents block', function () {
    $resolved = app(DocumentDisclaimerComposer::class)->resolveFromSnapshot([
        'document_type' => FinancialDocumentType::Invoice->value,
        'documents' => [
            'global_disclaimer' => 'Frozen invoice disclaimer.',
            'customer_type' => 'Retail',
            'customer_type_disclaimer' => 'Retail-specific language.',
            'authorization_language' => 'Sign here.',
        ],
    ]);

    expect($resolved['global_disclaimer'])->toBe('Frozen invoice disclaimer.')
        ->and($resolved['customer_type_disclaimer'])->toBe('Retail-specific language.');
});

test('shop settings can save document disclaimer fields', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->patch(route('operations.settings.shop.estimates.update'), [
        'estimate_disclaimer' => 'Updated estimate disclaimer.',
        'invoice_disclaimer' => 'Updated invoice disclaimer.',
        'recommendation_disclaimer' => 'Updated recommendation disclaimer.',
        'authorization_language' => 'Updated authorization language.',
        'customer_type_disclaimers' => [
            'retail' => 'Updated retail disclaimer.',
            'fleet' => 'Updated fleet disclaimer.',
        ],
        'estimate_validity_days' => 21,
    ])->assertRedirect(route('operations.settings.shop.edit'));

    $settings = ShopSettings::current()->fresh();

    expect($settings->estimate_disclaimer)->toBe('Updated estimate disclaimer.')
        ->and($settings->invoice_disclaimer)->toBe('Updated invoice disclaimer.')
        ->and($settings->authorization_language)->toBe('Updated authorization language.')
        ->and($settings->customerTypeDisclaimerMap()['retail'])->toBe('Updated retail disclaimer.')
        ->and($settings->customerTypeDisclaimerMap()['fleet'])->toBe('Updated fleet disclaimer.')
        ->and($settings->estimate_validity_days)->toBe(21);
});

function repairOrderForDocumentDisclaimer(string $customerType = 'Retail'): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Doc',
        'last_name' => 'Disclaimer',
        'phone' => '555-0111',
        'email' => 'doc-disclaimer@example.test',
        'customer_type' => $customerType,
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Brake inspection.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace front pads',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
    ]);

    return $repairOrder->fresh(['customer', 'vehicle', 'concerns.lines']);
}
