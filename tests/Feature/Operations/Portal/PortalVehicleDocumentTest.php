<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\DocumentPdfPath;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Mail\PortalAccessCodeMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    ShopSettings::current()->update(['shop_name' => 'Demo Auto Repair']);
});

test('portal customer can view and download a vehicle document pdf', function () {
    Mail::fake();

    [$customer, $vehicle, $document] = portalVehicleDocumentFixture();

    portalVehicleDocumentSignIn($customer);

    $viewResponse = $this->get(route('portal.vehicles.documents.view', [$vehicle, $document]))
        ->assertOk();

    expect($viewResponse->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($viewResponse->headers->get('Content-Disposition'))->toContain('inline');

    $downloadResponse = $this->get(route('portal.vehicles.documents.download', [$vehicle, $document]))
        ->assertOk();

    expect($downloadResponse->headers->get('Content-Disposition'))->toContain('attachment');

    $events = OperationalEvent::query()
        ->whereIn('event_name', [
            OperationalEventName::PortalDocumentViewed->value,
            OperationalEventName::PortalDocumentDownloaded->value,
        ])
        ->orderBy('id')
        ->get();

    expect($events)->toHaveCount(2)
        ->and($events[0]->event_name)->toBe(OperationalEventName::PortalDocumentViewed->value)
        ->and($events[1]->event_name)->toBe(OperationalEventName::PortalDocumentDownloaded->value)
        ->and($events[0]->aggregate_id)->toBe($document->id)
        ->and($events[0]->payload_json['customer_id'])->toBe($customer->id)
        ->and($events[0]->payload_json['vehicle_id'])->toBe($vehicle->id)
        ->and($events[0]->payload_json['document_type'])->toBe('invoice')
        ->and($events[0]->payload_json['portal_session_id'])->toBeString()->not->toBe('');
});

test('portal vehicle document access rejects another customers vehicle', function () {
    Mail::fake();

    [$owner, $vehicle, $document] = portalVehicleDocumentFixture();

    $other = Customer::query()->create([
        'first_name' => 'Other',
        'last_name' => 'Driver',
        'phone' => '7195558888',
        'email' => 'other.driver@example.test',
    ]);

    portalVehicleDocumentSignIn($other);

    $this->get(route('portal.vehicles.documents.view', [$vehicle, $document]))->assertNotFound();
    $this->get(route('portal.vehicles.documents.download', [$vehicle, $document]))->assertNotFound();
});

test('portal vehicle document access rejects document from another vehicle', function () {
    Mail::fake();

    [$customer, $vehicle, $document] = portalVehicleDocumentFixture();

    $otherVehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Subaru',
        'model' => 'Outback',
        'plate' => 'SUB21',
    ]);

    portalVehicleDocumentSignIn($customer);

    $this->get(route('portal.vehicles.documents.view', [$otherVehicle, $document]))->assertNotFound();
});

test('portal vehicle document access rejects documents without a viewable pdf', function () {
    Mail::fake();

    [$customer, $vehicle, $document] = portalVehicleDocumentFixture(withPdf: false);

    portalVehicleDocumentSignIn($customer);

    $this->get(route('portal.vehicles.documents.view', [$vehicle, $document]))->assertNotFound();
});

test('portal vehicle page shows view and download for documents with pdfs', function () {
    Mail::fake();

    [$customer, $vehicle] = portalVehicleDocumentFixture();

    portalVehicleDocumentSignIn($customer);

    $this->get(route('portal.vehicles.show', $vehicle))
        ->assertOk()
        ->assertSee('Invoice #1042')
        ->assertSee('View')
        ->assertSee('Download');
});

/**
 * @return array{0: Customer, 1: Vehicle, 2: EstimateDocument}
 */
function portalVehicleDocumentFixture(bool $withPdf = true): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => '7195551212',
        'email' => 'molly.documents@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'plate' => 'JEEP14',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Closed,
        'concern_summary' => 'Timing belt service',
        'closed_at' => Carbon::parse('2026-03-14 16:00:00'),
    ]);

    $document = EstimateDocument::query()->create([
        'repair_order_id' => $repairOrder->id,
        'document_type' => FinancialDocumentType::Invoice,
        'document_number' => 1042,
        'snapshot_json' => ['totals' => []],
        'status' => 'issued',
        'generated_at' => Carbon::parse('2026-03-14 17:00:00'),
    ]);

    if ($withPdf) {
        $path = DocumentPdfPath::for($document);
        Storage::disk('local')->put($path, '%PDF-1.4 portal test');

        $document->forceFill([
            'pdf_path' => $path,
        ])->save();
    }

    return [$customer, $vehicle, $document];
}

function portalVehicleDocumentSignIn(Customer $customer): void
{
    test()->post(route('portal.access.challenges.store'), [
        'contact' => $customer->email,
    ]);

    $sent = Mail::sent(PortalAccessCodeMail::class)->first();

    expect($sent)->not->toBeNull();

    test()->post(route('portal.access.verify.store'), [
        'code' => $sent->plainCode,
    ])->assertRedirect(route('portal.home'));
}
