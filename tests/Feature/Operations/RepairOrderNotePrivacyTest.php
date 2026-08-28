<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateSnapshotBuilder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('note lines longer than 255 characters can be saved', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForNotePrivacy();

    $description = str_repeat('x', 300);

    expect(strlen($description))->toBeGreaterThan(255);

    $this->actingAs($advisor)->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note->value,
        'description' => $description,
        'unit_price' => '0',
        'quantity' => '1',
    ])->assertRedirect();

    expect(RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->sole())
        ->description->toBe($description);
});

test('new note lines default to advisor only when shop private default is enabled', function () {
    ShopSettings::current()->update(['default_notes_private' => true]);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForNotePrivacy();

    $this->actingAs($advisor)->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note->value,
        'description' => 'Call customer before exceeding diagnostic authorization.',
        'unit_price' => '0',
        'quantity' => '1',
    ])->assertRedirect();

    $note = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->sole();

    expect($note->is_private)->toBeTrue()
        ->and($note->visible_to_advisor)->toBeTrue()
        ->and($note->visible_to_technician)->toBeFalse()
        ->and($note->visible_to_customer)->toBeFalse();
});

test('customer audience checkbox saves customer-visible note even when shop default is private', function () {
    ShopSettings::current()->update(['default_notes_private' => true]);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForNotePrivacy();

    $this->actingAs($advisor)->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note->value,
        'description' => 'Visible to customer on estimate.',
        'unit_price' => '0',
        'quantity' => '1',
        'visible_to_advisor' => '1',
        'visible_to_technician' => '0',
        'visible_to_customer' => '1',
    ])->assertRedirect();

    $note = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->sole();

    expect($note->is_private)->toBeFalse()
        ->and($note->visible_to_customer)->toBeTrue()
        ->and($note->visible_to_technician)->toBeFalse();
});

test('technician audience puts note on tech sheet while keeping it off customer estimate', function () {
    ShopSettings::current()->update(['default_notes_private' => true]);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForNotePrivacy();

    $this->actingAs($advisor)->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note->value,
        'description' => 'Bay reminder for tech only.',
        'unit_price' => '0',
        'quantity' => '1',
        'visible_to_advisor' => '1',
        'visible_to_technician' => '1',
        'visible_to_customer' => '0',
    ])->assertRedirect();

    $note = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->sole();

    expect($note->isVisibleToTechnician())->toBeTrue()
        ->and($note->isVisibleToCustomer())->toBeFalse()
        ->and($note->is_private)->toBeTrue();
});

test('customer estimate snapshot excludes private note lines from pdf html', function () {
    ShopSettings::current()->update(['default_notes_private' => true]);
    [$repairOrder, $concern] = repairOrderForNotePrivacy();

    $concern->update([
        'customer_states' => 'Customer reports vibration.',
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Road test and inspection',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Staff reminder only.',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
        'is_private' => true,
        'visible_to_advisor' => true,
        'visible_to_technician' => true,
        'visible_to_customer' => false,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Customer-facing note.',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
        'is_private' => false,
        'visible_to_advisor' => true,
        'visible_to_technician' => true,
        'visible_to_customer' => true,
    ]);

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['customer', 'vehicle', 'concerns.lines']));
    $presented = app(\App\Ark\Operations\Documents\DocumentPdfPresenter::class)->prepareForCustomer($snapshot);

    expect($presented['concerns'][0]['notes'] ?? null)->toBeNull();

    $html = view('operations.documents.pdf.document', [
        'snapshot' => $presented,
    ])->render();

    expect(collect($snapshot['concerns'][0]['lines'])->pluck('description')->all())
        ->toContain('Staff reminder only.')
        ->toContain('Customer-facing note.')
        ->and(collect($presented['concerns'][0]['lines'])->pluck('description')->all())
        ->not->toContain('Staff reminder only.')
        ->toContain('Customer-facing note.')
        ->and($html)
        ->not->toContain('Staff reminder only.')
        ->toContain('Customer-facing note.');
});

test('pdf concern header does not render retired advisor note field as supporting text', function () {
    $html = view('operations.documents.partials._pdf-concern', [
        'snapshot' => ['pdf_work_column_label' => 'Estimated Work'],
        'concern' => [
            'summary' => 'Brake vibration on highway',
            'subtotal' => '$150.00',
            'notes' => 'Most noticeable after sitting overnight.',
            'disposition' => 'approved',
            'disposition_label' => 'Approved',
            'customer_states' => null,
            'verified_findings' => null,
            'dtcs_summary' => null,
            'recommendation' => null,
            'recommendation_intent' => 'maintenance',
            'lines' => [],
        ],
        'duplicateCustomerStates' => false,
    ])->render();

    expect($html)
        ->not->toContain('concern-header-support')
        ->not->toContain('Most noticeable after sitting overnight.')
        ->toContain('Brake vibration on highway');
});

test('worksheet edit mode shows note line edit affordance', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForNotePrivacy();

    $note = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Advisor note to edit.',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
    ]);

    $this->actingAs($advisor)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-line-edit-trigger', false)
        ->assertSee('Edit Advisor note to edit.', false)
        ->assertSee(route('operations.repair-orders.show', [
            'repairOrder' => $repairOrder,
            'editing_line' => $note->id,
        ]), false);
});

test('multiline note lines preserve line breaks in worksheet and customer pdf html', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForNotePrivacy();

    $description = <<<'NOTE'
Tire Tread Depths
Front Driver: 2/32"
Front Passenger: 1/32"
Rear Driver: 9/32"
Rear Passenger: 9/32"

Brake Pad Measurements
Front Driver: 6 mm
NOTE;

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => $description,
        'quantity' => '1.00',
        'unit_price_cents' => 0,
        'is_private' => false,
    ]);

    $worksheetHtml = $this->actingAs($advisor)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();

    expect($worksheetHtml)
        ->toContain('ops-note-body')
        ->toContain('Tire Tread Depths')
        ->toContain('Front Driver: 2/32&quot;')
        ->toContain('Brake Pad Measurements');

    $snapshot = app(EstimateSnapshotBuilder::class)->build($repairOrder->fresh(['customer', 'vehicle', 'concerns.lines']));
    $presented = app(\App\Ark\Operations\Documents\DocumentPdfPresenter::class)->prepareForCustomer($snapshot);

    $pdfHtml = view('operations.documents.pdf.document', [
        'snapshot' => $presented,
    ])->render();

    expect($pdfHtml)
        ->toContain('ops-note-body')
        ->toContain('Tire Tread Depths')
        ->toContain('Front Driver: 2/32&quot;')
        ->toContain('Brake Pad Measurements');
});

test('worksheet note edit form includes line type for save', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForNotePrivacy();

    $note = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Original note text.',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
    ]);

    $this->actingAs($advisor)
        ->withSession([WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->get(route('operations.repair-orders.show', [
            'repairOrder' => $repairOrder,
            'editing_line' => $note->id,
        ]))
        ->assertOk()
        ->assertSee('id="line-update-'.$note->id.'"', false)
        ->assertSee('name="type" value="note"', false)
        ->assertSee('submitWorksheetForm($event)', false)
        ->assertSee('data-workspace-modal-delete-line', false)
        ->assertSee(route('operations.repair-orders.lines.destroy', [$repairOrder, $note]), false)
        ->assertDontSee('class="contents"', false);
});

test('advisor can delete a note line from the worksheet', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForNotePrivacy();

    $note = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Note to remove.',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
    ]);

    $this->actingAs($advisor)
        ->delete(route('operations.repair-orders.lines.destroy', [$repairOrder, $note]))
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    expect(RepairOrderLine::query()->whereKey($note->id)->exists())->toBeFalse();
});

test('advisor cannot save retired concern advisor note field from review mode', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForNotePrivacy();

    $concern->forceFill([
        'customer_states' => "Truck shakes uphill\nunder acceleration.",
        'notes' => 'Original advisor note.',
    ])->save();

    $this->actingAs($advisor)->patch(route('operations.repair-orders.concerns.update', [$repairOrder, $concern]), [
        'summary' => $concern->summary,
        'recommendation_intent' => $concern->recommendationIntent()->value,
        'customer_states' => $concern->customer_states,
        'verified_findings' => $concern->verified_findings,
        'dtcs_summary' => $concern->dtcs_summary,
        'recommendation' => $concern->recommendation,
        'notes' => 'Updated advisor note after loaded road test.',
        'return_mode' => 'review',
    ])->assertRedirect();

    expect($concern->fresh())
        ->notes->toBe('Original advisor note.')
        ->customer_states->toBe("Truck shakes uphill\nunder acceleration.");
});

test('advisor can edit note privacy and text from worksheet mode', function () {
    ShopSettings::current()->update(['default_notes_private' => false]);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForNotePrivacy();

    $note = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Original note text.',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
        'is_private' => false,
        'visible_to_advisor' => true,
        'visible_to_technician' => true,
        'visible_to_customer' => true,
    ]);

    $this->actingAs($advisor)->patch(route('operations.repair-orders.lines.update', [$repairOrder, $note]), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note->value,
        'description' => 'Updated worksheet note text.',
        'unit_price' => '0',
        'quantity' => '1',
        'visible_to_advisor' => '1',
        'visible_to_technician' => '0',
        'visible_to_customer' => '0',
    ])->assertRedirect(route('operations.repair-orders.show', $repairOrder).'#estimate-lines');

    $note->refresh();

    expect($note)
        ->description->toBe('Updated worksheet note text.')
        ->is_private->toBeTrue()
        ->and($note->visible_to_advisor)->toBeTrue()
        ->and($note->visible_to_technician)->toBeFalse()
        ->and($note->visible_to_customer)->toBeFalse();
});

test('advisor can edit note audience and text from review mode', function () {
    ShopSettings::current()->update(['default_notes_private' => false]);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    [$repairOrder, $concern] = repairOrderForNotePrivacy();

    $note = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Original note text.',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
        'is_private' => false,
        'visible_to_advisor' => true,
        'visible_to_technician' => true,
        'visible_to_customer' => true,
    ]);

    $this->actingAs($advisor)->patch(route('operations.repair-orders.lines.update', [$repairOrder, $note]), [
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Note->value,
        'description' => 'Updated private note text.',
        'unit_price' => '0',
        'quantity' => '1',
        'visible_to_advisor' => '1',
        'visible_to_technician' => '1',
        'visible_to_customer' => '0',
        'return_mode' => 'review',
    ])->assertRedirect();

    $note->refresh();

    expect($note)
        ->description->toBe('Updated private note text.')
        ->is_private->toBeTrue()
        ->and($note->visible_to_technician)->toBeTrue()
        ->and($note->visible_to_customer)->toBeFalse();
});

/**
 * @return array{0: RepairOrder, 1: RepairOrderConcern}
 */
function repairOrderForNotePrivacy(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Casey',
        'last_name' => 'Morgan',
        'phone' => '555-0111',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Ford',
        'model' => 'F-150',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Vibration at highway speed',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Vibration at highway speed',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    return [$repairOrder->fresh(['customer', 'vehicle', 'concerns.lines']), $concern];
}
