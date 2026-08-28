<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Carbon;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\HtmlPdfBuilder;
use App\Ark\Operations\Documents\OperationalSheetPresenter;
use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\RepairOrders\RepairActionOwnerType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('operational sheet printed timestamp uses display timezone', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-05 02:42:00', 'UTC'));
    ShopSettings::current()->update(['shop_timezone' => 'America/Denver']);
    ShopDisplayTimezone::apply();

    [$repairOrder] = repairOrderForOperationalSheets();

    $sheet = app(OperationalSheetPresenter::class)->intake($repairOrder);

    expect($sheet['printed_at'])->toBe('Jun 4, 2026 8:42 PM');
});

test('operational sheet presenter builds intake concerns from repair order', function () {
    [$repairOrder] = repairOrderForOperationalSheets();

    $sheet = app(OperationalSheetPresenter::class)->intake($repairOrder);

    expect($sheet['sheet_type'])->toBe('intake')
        ->and($sheet['concerns'])->toHaveCount(2)
        ->and($sheet['concerns'][0]['summary'])->toBe('Brake noise on stop')
        ->and($sheet['concerns'][0]['customer_states'])->toBe('Grinding when stopping')
        ->and($sheet['advisor_name'])->toBe('Front Desk Advisor');
});

test('operational sheet presenter tech sheet includes approved concerns labor and parts only', function () {
    [$repairOrder] = repairOrderForOperationalSheets();

    $sheet = app(OperationalSheetPresenter::class)->tech($repairOrder);

    expect($sheet['sheet_type'])->toBe('tech')
        ->and($sheet['title'])->toBe('Technician Work Order')
        ->and($sheet['has_approved_work'])->toBeTrue()
        ->and($sheet['concerns'])->toHaveCount(1)
        ->and($sheet['concerns'][0]['summary'])->toBe('Replace front pads and rotors')
        ->and($sheet['concerns'][0]['labor_hours'])->toBe('2.5')
        ->and($sheet['approved_flag_hours'])->toBe('2.5')
        ->and($sheet['total_labor_hours'])->toBe('2.5')
        ->and($sheet['work_station_label'])->toBe('Unassigned')
        ->and($sheet['technician_name'])->toBe('Bay Technician')
        ->and($sheet['vin_display'])->toBe('…J3333333')
        ->and($sheet['concerns'][0]['parts'])->toHaveCount(2)
        ->and($sheet['concerns'][0]['parts'][0]['part_number'])->toBe('BRK-4410')
        ->and($sheet['concerns'][0]['labor'][0]['label'])->toContain('2.5 hrs')
        ->and($sheet['concerns'][0]['parts'][0]['label'])->toContain('Qty 1')
        ->and(collect($sheet['concerns'])->pluck('summary')->all())->not->toContain('Brake noise on stop');
});

test('tech sheet excludes sublet quantity from approved flag hours', function () {
    [$repairOrder] = repairOrderForOperationalSheets();
    $approved = $repairOrder->concerns->firstWhere('summary', 'Replace front pads and rotors');
    $workGroup = $approved->workGroups->first();

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $approved->id,
        'repair_order_work_group_id' => $workGroup->id,
        'type' => RepairOrderLineType::Sublet,
        'description' => 'Four-wheel alignment',
        'quantity' => '1.50',
        'unit_price_cents' => 12900,
        'subtotal_cents' => 12900,
        'tax_cents' => 0,
        'total_cents' => 12900,
        'vendor_name' => 'Hunter Align',
    ]);

    $sheet = app(OperationalSheetPresenter::class)->tech($repairOrder->fresh());

    expect($sheet['approved_flag_hours'])->toBe('2.5')
        ->and($sheet['concerns'][0]['labor_hours'])->toBe('2.5')
        ->and($sheet['packages'][0]['sublets'])->toHaveCount(1)
        ->and($sheet['packages'][0]['sublets'][0]['label'])->toContain('Four-wheel alignment')
        ->and(collect($sheet['packages'][0]['labor'])->pluck('description')->all())
        ->not->toContain('Four-wheel alignment');
});

test('operational sheets show advisor from estimate snapshot', function () {
    [$repairOrder, $advisor] = repairOrderForOperationalSheets();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.estimate-documents.store', $repairOrder))
        ->assertRedirect();

    $presenter = app(OperationalSheetPresenter::class);
    $freshRepairOrder = $repairOrder->fresh(['customer', 'vehicle', 'assignedTechnician', 'concerns', 'concerns.lines', 'estimateDocuments.creator']);

    $intakeHtml = view('operations.documents.sheets.intake', [
        'sheet' => $presenter->intake($freshRepairOrder),
    ])->render();

    expect($intakeHtml)->toContain('Advisor: Front Desk Advisor');
});

test('operational sheets always show mileage capture fields with verify when values are known', function () {
    [$repairOrder] = repairOrderForOperationalSheets();
    $presenter = app(OperationalSheetPresenter::class);

    $missingBothHtml = view('operations.documents.sheets.intake', [
        'sheet' => $presenter->intake($repairOrder),
    ])->render();

    expect($missingBothHtml)
        ->toContain('mileage-capture-label">Mileage in</span>')
        ->toContain('mileage-capture-label">Mileage out</span>')
        ->not->toContain('class="mileage-capture-verify"')
        ->not->toContain('class="mileage-capture-value"')
        ->toContain('Mileage: — / —');

    $repairOrder->update(['mileage_in' => 165604]);
    $freshRepairOrder = $repairOrder->fresh(['customer', 'vehicle', 'assignedTechnician', 'concerns.lines']);

    $missingOutHtml = view('operations.documents.sheets.intake', [
        'sheet' => $presenter->intake($freshRepairOrder),
    ])->render();

    expect($missingOutHtml)
        ->toContain('mileage-capture-value">165,604</p>')
        ->toContain('class="mileage-capture-verify"')
        ->toContain('Mileage: 165,604 / —');

    $repairOrder->update(['mileage_out' => 165892]);
    $freshRepairOrder = $repairOrder->fresh(['customer', 'vehicle', 'assignedTechnician', 'concerns.lines']);

    $completeHtml = view('operations.documents.sheets.intake', [
        'sheet' => $presenter->intake($freshRepairOrder),
    ])->render();

    expect($completeHtml)
        ->toContain('mileage-capture-value">165,604</p>')
        ->toContain('mileage-capture-value">165,892</p>')
        ->toContain('class="mileage-capture-verify"')
        ->toContain('Mileage: 165,604 / 165,892');
});

test('operational sheets always show advisor and technician capture with prefill when assigned', function () {
    [$repairOrder] = repairOrderForOperationalSheets();
    $presenter = app(OperationalSheetPresenter::class);

    $assignedHtml = view('operations.documents.sheets.intake', [
        'sheet' => $presenter->intake($repairOrder),
    ])->render();

    expect($assignedHtml)
        ->toContain('staff-capture-label">Advisor name</span>')
        ->toContain('staff-capture-label">Technician name</span>')
        ->toContain('staff-capture-value">Front Desk Advisor</p>')
        ->toContain('staff-capture-value">Bay Technician</p>');

    $techHtml = view('operations.documents.sheets.tech', [
        'sheet' => $presenter->tech($repairOrder),
    ])->render();

    expect($techHtml)
        ->toContain('Bay Technician')
        ->toContain('Work Station')
        ->toContain('Unassigned')
        ->not->toContain('staff-capture-label');

    $repairOrder->estimateDocuments()->update([
        'created_by' => null,
        'snapshot_json' => [],
    ]);
    $repairOrder->update(['assigned_technician_id' => null]);
    $freshRepairOrder = $repairOrder->fresh([
        'customer',
        'vehicle',
        'assignedTechnician',
        'concerns',
        'concerns.lines',
        'estimateDocuments.creator',
    ]);

    $unassignedIntakeHtml = view('operations.documents.sheets.intake', [
        'sheet' => $presenter->intake($freshRepairOrder),
    ])->render();

    expect($unassignedIntakeHtml)
        ->toContain('staff-capture-label">Advisor name</span>')
        ->toContain('staff-capture-label">Technician name</span>')
        ->not->toContain('class="staff-capture-value"');

    $unassignedTechHtml = view('operations.documents.sheets.tech', [
        'sheet' => $presenter->tech($freshRepairOrder),
    ])->render();

    expect($unassignedTechHtml)
        ->toContain('Technician')
        ->toContain('Unassigned');
});

test('tech sheet presenter reports empty state when no approved concerns exist', function () {
    [$repairOrder] = repairOrderForOperationalSheets(approvedWork: false);

    $sheet = app(OperationalSheetPresenter::class)->tech($repairOrder);

    expect($sheet['has_approved_work'])->toBeFalse()
        ->and($sheet['concerns'])->toBe([])
        ->and($sheet['total_labor_hours'])->toBe('0');

    $html = view('operations.documents.sheets.tech', ['sheet' => $sheet])->render();

    expect($html)->toContain('No approved concerns yet');
});

test('operational sheets omit referral source from identity band', function () {
    [$repairOrder] = repairOrderForOperationalSheets();
    $repairOrder->customer->update(['referral_source' => EncounterSource::Website->value]);
    $repairOrder->load('customer');

    $presenter = app(OperationalSheetPresenter::class);

    $intakeHtml = view('operations.documents.sheets.intake', [
        'sheet' => $presenter->intake($repairOrder),
    ])->render();

    $techHtml = view('operations.documents.sheets.tech', [
        'sheet' => $presenter->tech($repairOrder),
    ])->render();

    expect($intakeHtml)->not->toContain('Referral:')
        ->and($techHtml)->not->toContain('Referral:');
});

test('operational sheet blade views render intake and tech content', function () {
    [$repairOrder] = repairOrderForOperationalSheets();
    $presenter = app(OperationalSheetPresenter::class);

    $intakeHtml = view('operations.documents.sheets.intake', [
        'sheet' => $presenter->intake($repairOrder),
    ])->render();

    $techHtml = view('operations.documents.sheets.tech', [
        'sheet' => $presenter->tech($repairOrder),
    ])->render();

    expect($intakeHtml)
        ->toContain('concern--intent-')
        ->toContain('identity-band')
        ->toContain('Customer')
        ->toContain('Vehicle')
        ->toContain('Visit')
        ->toContain('Check In · RO #')
        ->toContain('Keys / Check In')
        ->toContain('Advisor: Front Desk Advisor')
        ->toContain('Customer Concerns')
        ->toContain('Advisor Check In')
        ->toContain('worksheet-capture">DTC report</div>')
        ->not->toContain('Bay Check In')
        ->not->toContain('worksheet-capture">Mileage in</div>')
        ->toContain('Grinding when stopping')
        ->and($techHtml)
        ->toContain('Technician Work Order')
        ->toContain('Approved Flag Hours')
        ->toContain('2.5 HOURS')
        ->toContain('shop production record')
        ->toContain('Work Station')
        ->toContain('Unassigned')
        ->toContain('>Labor</p>')
        ->toContain('>Parts</p>')
        ->toContain('>Work Notes</p>')
        ->toContain('BRK-4410')
        ->toContain('2.5 hrs')
        ->toContain('Qty 1')
        ->toContain('Work note')
        ->toContain('Customer prefers OEM pads if available.')
        ->toContain('Recheck brake noise after pad bedding.')
        ->not->toContain('Advisor notes')
        ->not->toContain('identity-band')
        ->not->toContain('Tech · RO #')
        ->not->toContain('Preliminary')
        ->not->toContain('$145.00')
        ->not->toContain('sheet-table--tech-pull');
});

test('tech sheet presenter includes technician-audience note lines on approved concerns', function () {
    [$repairOrder] = repairOrderForOperationalSheets();

    $sheet = app(OperationalSheetPresenter::class)->tech($repairOrder);
    $workNotes = collect($sheet['concerns'][0]['work_notes']);

    expect($workNotes->pluck('description')->all())->toContain('Customer prefers OEM pads if available.')
        ->and($workNotes->pluck('description')->all())->toContain('Recheck brake noise after pad bedding.')
        ->and($workNotes->where('label', 'Work note')->pluck('description')->all())
        ->toContain('Customer prefers OEM pads if available.')
        ->toContain('Recheck brake noise after pad bedding.');
});

test('tech sheet presenter excludes advisor-only notes', function () {
    [$repairOrder] = repairOrderForOperationalSheets();

    RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('type', RepairOrderLineType::Note)
        ->update([
            'visible_to_advisor' => true,
            'visible_to_technician' => false,
            'visible_to_customer' => false,
            'is_private' => true,
        ]);

    $sheet = app(OperationalSheetPresenter::class)->tech($repairOrder->fresh([
        'customer',
        'vehicle',
        'assignedTechnician',
        'concerns.lines',
        'concerns.workGroups.lines',
        'concerns.workGroups.ownerUser',
        'estimateDocuments.creator',
    ]));
    $workNotes = collect($sheet['concerns'][0]['work_notes'] ?? []);

    expect($workNotes->where('label', 'Work note')->pluck('description')->all())
        ->not->toContain('Customer prefers OEM pads if available.')
        ->not->toContain('Recheck brake noise after pad bedding.');
});

test('authorized staff can open intake and tech sheet pdfs', function () {
    [$repairOrder] = repairOrderForOperationalSheets();

    $pdf = Mockery::mock(HtmlPdfBuilder::class);
    $pdf->shouldReceive('toPdfBytes')->twice()->andReturn('%PDF-fake');
    app()->instance(HtmlPdfBuilder::class, $pdf);

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->get(route('operations.repair-orders.sheets.intake.pdf', $repairOrder))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="intake-ro-'.$repairOrder->repair_order_id.'.pdf"');

    $this->get(route('operations.repair-orders.sheets.tech.pdf', $repairOrder))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="tech-ro-'.$repairOrder->repair_order_id.'.pdf"');
});

test('repair order toolbar exposes intake and tech sheet links', function () {
    [$repairOrder] = repairOrderForOperationalSheets();

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Check In sheet')
        ->assertSee('Tech sheet')
        ->assertSee(route('operations.repair-orders.sheets.intake.pdf', $repairOrder))
        ->assertSee(route('operations.repair-orders.sheets.tech.pdf', $repairOrder));
});

/**
 * @return array{0: RepairOrder, 1: User}
 */
function repairOrderForOperationalSheets(bool $approvedWork = true): array
{
    $advisor = User::factory()->create(['name' => 'Front Desk Advisor'])->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create(['name' => 'Bay Technician'])->assignRole(ArkRole::Technician->value);

    $customer = Customer::query()->create([
        'first_name' => 'Jordan',
        'last_name' => 'Lee',
        'phone' => '555-0199',
        'email' => 'jordan@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Subaru',
        'model' => 'Outback',
        'vin' => '4S4BSANC2J3333333',
        'plate' => 'OUTBK18',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'assigned_technician_id' => $technician->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brake noise on stop',
        'waiting_here' => true,
    ]);

    EstimateDocument::query()->create([
        'repair_order_id' => $repairOrder->id,
        'document_type' => FinancialDocumentType::Estimate,
        'document_number' => 1,
        'snapshot_json' => ['generated_by' => ['name' => $advisor->name]],
        'status' => 'draft',
        'created_by' => $advisor->id,
    ]);

    $recommendedConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake noise on stop',
        'customer_states' => 'Grinding when stopping',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'immediate_attention',
        'position' => 1,
    ]);

    $approvedConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Replace front pads and rotors',
        'customer_states' => 'Approved prior brake work',
        'verified_findings' => 'Front pads at 2mm, rotors glazed',
        'dtcs_summary' => 'None stored',
        'recommendation' => 'Replace front pads and machine rotors',
        'disposition' => $approvedWork ? RepairOrderConcernDisposition::Approved : RepairOrderConcernDisposition::Recommended,
        'position' => 2,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $recommendedConcern->id,
        'type' => RepairOrderLineType::Note,
        'description' => 'Customer noticed last week.',
        'quantity' => '1.00',
        'unit_price_cents' => 0,
        'is_private' => true,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $recommendedConcern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake inspection',
        'quantity' => '1.00',
        'unit_price_cents' => 12000,
    ]);

    if ($approvedWork) {
        $workGroup = RepairOrderWorkGroup::query()->create([
            'repair_order_concern_id' => $approvedConcern->id,
            'title' => 'Replace front pads and rotors',
            'position' => 1,
            'owner_type' => RepairActionOwnerType::Technician,
            'owner_user_id' => $technician->id,
        ]);

        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $approvedConcern->id,
            'repair_order_work_group_id' => $workGroup->id,
            'type' => RepairOrderLineType::Labor,
            'description' => 'Front brake pad and rotor replacement',
            'quantity' => '2.50',
            'unit_price_cents' => 14500,
        ]);

        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $approvedConcern->id,
            'repair_order_work_group_id' => $workGroup->id,
            'type' => RepairOrderLineType::Part,
            'description' => 'Front brake pad set',
            'part_number' => 'BRK-4410',
            'vendor_name' => 'Worldpac',
            'quantity' => '1.00',
            'unit_price_cents' => 8900,
            'has_core' => true,
            'save_old_part' => true,
        ]);

        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $approvedConcern->id,
            'repair_order_work_group_id' => $workGroup->id,
            'type' => RepairOrderLineType::Part,
            'description' => 'Brake hardware kit',
            'quantity' => '1.00',
            'unit_price_cents' => 1200,
        ]);

        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $approvedConcern->id,
            'repair_order_work_group_id' => $workGroup->id,
            'type' => RepairOrderLineType::Note,
            'description' => 'Customer prefers OEM pads if available.',
            'quantity' => '1.00',
            'unit_price_cents' => 0,
            'is_private' => true,
        ]);

        RepairOrderLine::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $approvedConcern->id,
            'repair_order_work_group_id' => $workGroup->id,
            'type' => RepairOrderLineType::Note,
            'description' => 'Recheck brake noise after pad bedding.',
            'quantity' => '1.00',
            'unit_price_cents' => 0,
            'is_private' => true,
        ]);
    }

    return [$repairOrder->fresh(['customer', 'vehicle', 'assignedTechnician', 'concerns.lines', 'concerns.workGroups.lines', 'concerns.workGroups.ownerUser', 'estimateDocuments.creator']), $advisor];
}
