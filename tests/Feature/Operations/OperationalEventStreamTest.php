<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('operational events record after successful server mutations with minimal payloads', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');
    $this->app->bind(PdfRenderer::class, OperationalEventFakePdfRenderer::class);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $customer = Customer::query()->create([
        'first_name' => 'Event',
        'last_name' => 'Customer',
        'phone' => '555-0101',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'EVT001',
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $this->post(route('operations.customers.repair-orders.drafts.store', $customer), [
        'vehicle_id' => $vehicle->id,
        'concern_summary' => 'Customer states brakes pulse.',
    ])->assertRedirect();

    $repairOrder = RepairOrder::query()->firstOrFail();
    $concern = RepairOrderConcern::query()->firstOrFail();

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Inspect brakes',
        'quantity' => '1.00',
        'unit_price' => '150.00',
    ])->assertRedirect();

    $line = RepairOrderLine::query()->firstOrFail();

    $this->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Inspect front brakes',
        'quantity' => '1.20',
        'unit_price' => '150.00',
    ])->assertRedirect();

    $this->patchJson(route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]), [
        'disposition' => RepairOrderConcernDisposition::Approved->value,
    ])->assertOk();

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder))
        ->assertRedirect();

    $this->delete(route('operations.repair-orders.lines.destroy', [$repairOrder, $line]))
        ->assertRedirect();

    $events = OperationalEvent::query()->orderBy('id')->get();

    expect($events->pluck('event_name')->all())->toBe([
        OperationalEventName::RepairOrderCreated->value,
        OperationalEventName::ConcernCreated->value,
        OperationalEventName::RepairOrderLifecycleChanged->value,
        OperationalEventName::EstimateLineAdded->value,
        OperationalEventName::EstimateLineUpdated->value,
        OperationalEventName::ConcernDispositionChanged->value,
        OperationalEventName::EstimateDocumentGenerated->value,
        OperationalEventName::EstimateLineDeleted->value,
    ]);

    $created = $events->firstWhere('event_name', OperationalEventName::RepairOrderCreated->value);
    $lineAdded = $events->firstWhere('event_name', OperationalEventName::EstimateLineAdded->value);
    $documentGenerated = $events->firstWhere('event_name', OperationalEventName::EstimateDocumentGenerated->value);

    expect($created)
        ->aggregate_type->toBe(RepairOrder::class)
        ->aggregate_id->toBe($repairOrder->id)
        ->actor_user_id->toBe($advisor->id)
        ->and($created->payload_json)->toHaveCount(3)
        ->and($created->payload_json['customer_id'])->toBe($customer->id)
        ->and($created->payload_json['vehicle_id'])->toBe($vehicle->id)
        ->and($created->payload_json['status'])->toBe(RepairOrderStatus::Draft->value)
        ->and($lineAdded->payload_json)->toHaveKeys(['line_id', 'concern_id', 'type', 'subtotal_cents', 'total_cents'])
        ->and($lineAdded->payload_json)->not->toHaveKeys(['description', 'snapshot_json', 'totals'])
        ->and($documentGenerated->payload_json)->toHaveKeys(['repair_order_id', 'estimate_document_id', 'document_number', 'status'])
        ->and($documentGenerated->payload_json['repair_order_id'])->toBe($repairOrder->repair_order_id)
        ->and($documentGenerated->payload_json['document_number'])->toBe(1);
});

test('vehicle decode records only successful server-side decode events', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    config()->set('services.partstech.username', null);
    config()->set('services.partstech.api_key', null);

    Http::fake([
        'vpic.nhtsa.dot.gov/*' => Http::response([
            'Results' => [[
                'ModelYear' => '2019',
                'Make' => 'Toyota',
                'Model' => 'RAV4',
                'Trim' => 'XLE',
                'EngineModel' => '2.5L',
                'DriveType' => 'All-Wheel Drive',
                'TransmissionStyle' => 'Automatic',
            ]],
        ]),
    ]);

    $this->postJson(route('operations.vehicles.decode-vin'), [
        'vin' => '2T3RFREV6KW020202',
    ])->assertOk();

    $event = OperationalEvent::query()->sole();

    expect($event->event_name)->toBe(OperationalEventName::VehicleDecoded->value)
        ->and($event->aggregate_type)->toBe('vehicle_identity')
        ->and($event->payload_json)->toHaveCount(3)
        ->and($event->payload_json['normalized_vin'])->toBe('2T3RFREV6KW020202')
        ->and($event->payload_json['normalized_vehicle_key'])->toBe('2019-toyota-rav4-xle-2-5l-awd-automatic')
        ->and($event->payload_json['source'])->toBe('nhtsa');
});

test('failed and unauthorized mutations do not emit operational events', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $customer = Customer::query()->create([
        'first_name' => 'No',
        'last_name' => 'Event',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $this->actingAs($technician)
        ->post(route('operations.customers.repair-orders.drafts.store', $customer), [
            'vehicle_id' => $vehicle->id,
            'concern_summary' => 'Should not be created.',
        ])->assertForbidden();

    expect(OperationalEvent::query()->count())->toBe(0);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->post(route('operations.customers.repair-orders.drafts.store', $customer), [
            'vehicle_id' => 999999,
            'concern_summary' => 'Invalid vehicle.',
        ])->assertSessionHasErrors('vehicle_id');

    expect(OperationalEvent::query()->count())->toBe(0);
});

test('operational events are immutable and reject command names', function () {
    $event = app(OperationalEventRecorder::class)->record(
        OperationalEventName::RepairOrderCreated,
        RepairOrder::class,
        aggregateId: 123,
        payload: ['customer_id' => 456],
    );

    expect(fn () => $event->update(['event_name' => 'repair_order_changed']))
        ->toThrow(LogicException::class, 'Operational events are immutable.');

    expect(fn () => $event->delete())
        ->toThrow(LogicException::class, 'Operational events are append-only.');

    expect(fn () => app(OperationalEventRecorder::class)->record(
        'create_repair_order',
        RepairOrder::class,
        aggregateId: 123,
    ))->toThrow(InvalidArgumentException::class, 'historical facts');
});

class OperationalEventFakePdfRenderer implements PdfRenderer
{
    public function renderEstimate(EstimateDocument $document): string
    {
        $path = 'estimate-documents/test/event-'.$document->id.'.pdf';

        Storage::disk('local')->put($path, 'PDF');

        $document->forceFill([
            'status' => 'generated',
            'pdf_path' => $path,
            'generated_at' => now(),
        ])->save();

        return $path;
    }
}
