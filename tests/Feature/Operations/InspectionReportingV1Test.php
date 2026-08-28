<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\HtmlPdfBuilder;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionCustomerEvidenceAllowlist;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionItemMeasurement;
use App\Ark\Operations\Inspections\InspectionItemPhoto;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Inspections\InspectionPhotoPurpose;
use App\Ark\Operations\Inspections\InspectionReportProjection;
use App\Ark\Operations\Portal\CreateOrReuseInspectionAccessTokenAction;
use App\Ark\Operations\Portal\CustomerReportQrCode;
use App\Ark\Operations\Portal\InspectionAccessToken;
use App\Ark\Operations\Portal\PortalInspectionPage;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('simple and detailed report modes share the same inspection authority', function () {
    $repairOrder = reportingRepairOrder();
    $projection = app(InspectionReportProjection::class);

    $simple = $projection->for($repairOrder, InspectionReportProjection::MODE_SIMPLE);
    $detailed = $projection->for($repairOrder, InspectionReportProjection::MODE_DETAILED);

    expect($simple['summary']['headline_needs_attention'])->toBe(2)
        ->and($simple['summary']['failed_count'])->toBe(1)
        ->and($simple['summary']['needs_attention_count'])->toBe(1)
        ->and($detailed['summary'])->toBe($simple['summary'])
        ->and(collect($simple['attention_findings'])->pluck('condition_value')->all())
        ->toContain('failed')
        ->toContain('needs_attention')
        ->and(collect($detailed['points'])->pluck('id')->sort()->values()->all())
        ->toBe(collect($simple['points'])->pluck('id')->sort()->values()->all());
});

test('customer evidence allowlist hides internal and unknown purposes everywhere', function () {
    expect(InspectionCustomerEvidenceAllowlist::includes(InspectionPhotoPurpose::Customer))->toBeTrue()
        ->and(InspectionCustomerEvidenceAllowlist::includes(InspectionPhotoPurpose::Before))->toBeTrue()
        ->and(InspectionCustomerEvidenceAllowlist::includes(InspectionPhotoPurpose::After))->toBeTrue()
        ->and(InspectionCustomerEvidenceAllowlist::includes(InspectionPhotoPurpose::Internal))->toBeFalse()
        ->and(InspectionCustomerEvidenceAllowlist::includes('warranty_internal'))->toBeFalse()
        ->and(InspectionCustomerEvidenceAllowlist::includes(''))->toBeFalse()
        ->and(InspectionCustomerEvidenceAllowlist::includes(null))->toBeFalse();

    $repairOrder = reportingRepairOrder(withPhotos: true);
    $report = app(InspectionReportProjection::class)->for($repairOrder, InspectionReportProjection::MODE_DETAILED);

    $allPhotoIds = collect($report['points'])->flatMap(fn (array $p) => collect($p['photos'])->pluck('id'))->all();
    $allVideoIds = collect($report['points'])->flatMap(fn (array $p) => collect($p['videos'])->pluck('id'))->all();

    expect($allPhotoIds)->toContain(
        InspectionItemPhoto::query()->where('purpose', InspectionPhotoPurpose::Customer->value)->value('id')
    )->and($allPhotoIds)->not->toContain(
        InspectionItemPhoto::query()->where('purpose', InspectionPhotoPurpose::Internal->value)->value('id')
    )->and($allVideoIds)->not->toBeEmpty();
});

test('structured brake and tire measurements render in projection', function () {
    $repairOrder = reportingRepairOrder();
    $report = app(InspectionReportProjection::class)->for($repairOrder, InspectionReportProjection::MODE_SIMPLE);

    $brake = collect($report['attention_findings'])->firstWhere('label', 'LF brake pads');
    $tire = collect($report['attention_findings'])->firstWhere('label', 'LF tire tread');

    expect($brake['measurement_presentation']['kind'])->toBe('brake_pads')
        ->and($brake['measurement_presentation']['lines'])->toContain('Inboard 3.0 mm')
        ->and($brake['measurement_presentation']['lines'])->toContain('Outboard 7.0 mm')
        ->and($tire['measurement_presentation']['kind'])->toBe('tire_tread')
        ->and($tire['measurement_presentation']['lines'][0])->toContain('Outer')
        ->and($tire['comparison_observations'])->not->toBeEmpty();
});

test('token portal shows simple report without minting for display path', function () {
    $repairOrder = reportingRepairOrder();
    $plain = str_repeat('a', 64);
    InspectionAccessToken::createForPlainToken($repairOrder, $plain);

    $before = InspectionAccessToken::query()->count();

    $this->get(route('portal.inspections.show', ['token' => $plain]))
        ->assertOk()
        ->assertSee('Vehicle Inspection')
        ->assertSee('Need Attention')
        ->assertSee('LF brake pads')
        ->assertSee('Failed')
        ->assertSee('Checked &amp; OK', false)
        ->assertSee('Road test');

    expect(InspectionAccessToken::query()->count())->toBe($before);
});

test('signed-in portal authorization shows report without creating access token', function () {
    $repairOrder = reportingRepairOrder();
    $customer = $repairOrder->customer;
    $vehicle = $repairOrder->vehicle;

    $before = InspectionAccessToken::query()->count();

    $this->actingAs($customer, 'portal')
        ->get(route('portal.vehicles.inspections.show', [
            'vehicle' => $vehicle,
            'repairOrder' => $repairOrder,
            'view' => 'detailed',
        ]))
        ->assertOk()
        ->assertSee('Vehicle Inspection')
        ->assertSee('LF brake pads')
        ->assertSee('Detailed');

    expect(InspectionAccessToken::query()->count())->toBe($before);
});

test('unauthorized customer cannot read another customers inspection', function () {
    $repairOrder = reportingRepairOrder();
    $other = Customer::query()->create([
        'first_name' => 'Other',
        'last_name' => 'Owner',
        'email' => 'other-owner@example.com',
        'phone' => '7195559999',
    ]);

    $this->actingAs($other, 'portal')
        ->get(route('portal.vehicles.inspections.show', [
            'vehicle' => $repairOrder->vehicle,
            'repairOrder' => $repairOrder,
        ]))
        ->assertForbidden();
});

test('print and pdf consume shared projection and qr uses safe share url', function () {
    $repairOrder = reportingRepairOrder(withPhotos: true);
    $plain = str_repeat('b', 64);
    InspectionAccessToken::createForPlainToken($repairOrder, $plain);

    $print = $this->get(route('portal.inspections.print', ['token' => $plain, 'view' => 'simple']));
    $print->assertOk()
        ->assertSee('Need Attention')
        ->assertSee('LF brake pads')
        ->assertSee('Inboard 3.0 mm')
        ->assertSee('Video evidence')
        ->assertSee('View video online')
        ->assertSee(route('portal.inspections.show', ['token' => $plain]), false)
        ->assertSee('data:image/svg+xml;base64,', false)
        ->assertDontSee('/app/');

    $pdf = Mockery::mock(HtmlPdfBuilder::class);
    $pdf->shouldReceive('toPdfBytes')
        ->once()
        ->withArgs(function (string $html) use ($plain): bool {
            return str_contains($html, 'LF brake pads')
                && str_contains($html, 'data:image/svg+xml;base64,')
                && str_contains($html, route('portal.inspections.show', ['token' => $plain]))
                && ! str_contains($html, '/app/');
        })
        ->andReturn('%PDF-1.4 mock');
    app()->instance(HtmlPdfBuilder::class, $pdf);

    $this->get(route('portal.inspections.pdf', ['token' => $plain, 'view' => 'detailed']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('staff preview print qr uses durable share not the one-hour preview token', function () {
    $repairOrder = reportingRepairOrder();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $preview = app(CreateOrReuseInspectionAccessTokenAction::class)
        ->execute($repairOrder, $advisor, forStaffPreview: true);

    expect($preview->token->expires_at)->not->toBeNull();

    $before = InspectionAccessToken::query()->count();

    $this->get(route('portal.inspections.print', ['token' => $preview->plainToken, 'view' => 'simple']))
        ->assertOk()
        ->assertSee('data:image/svg+xml;base64,', false)
        ->assertDontSee(route('portal.inspections.show', ['token' => $preview->plainToken]), false);

    expect(InspectionAccessToken::query()->count())->toBeGreaterThan($before);

    $durable = InspectionAccessToken::query()
        ->where('repair_order_id', $repairOrder->id)
        ->whereNull('expires_at')
        ->latest('id')
        ->first();

    expect($durable)->not->toBeNull();
});

test('staff inspection pdf uses repair portal live qr', function () {
    $repairOrder = reportingRepairOrder();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $pdf = Mockery::mock(HtmlPdfBuilder::class);
    $pdf->shouldReceive('toPdfBytes')
        ->once()
        ->withArgs(function (string $html): bool {
            return str_contains($html, 'LF brake pads')
                && str_contains($html, 'data:image/svg+xml;base64,')
                && (bool) preg_match('#/r/[a-z0-9]+/inspection#', $html);
        })
        ->andReturn('%PDF-1.4 mock');
    app()->instance(HtmlPdfBuilder::class, $pdf);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.inspection.pdf', $repairOrder))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Inspection PDF', false)
        ->assertDontSee('>Inspection report</a>', false);
});

test('auth print mints share token only for qr not for signed-in display', function () {
    $repairOrder = reportingRepairOrder();
    $customer = $repairOrder->customer;

    expect(InspectionAccessToken::query()->count())->toBe(0);

    $this->actingAs($customer, 'portal')
        ->get(route('portal.vehicles.inspections.show', [
            'vehicle' => $repairOrder->vehicle,
            'repairOrder' => $repairOrder,
        ]))
        ->assertOk();

    expect(InspectionAccessToken::query()->count())->toBe(0);

    $this->actingAs($customer, 'portal')
        ->get(route('portal.vehicles.inspections.print', [
            'vehicle' => $repairOrder->vehicle,
            'repairOrder' => $repairOrder,
            'view' => 'simple',
        ]))
        ->assertOk()
        ->assertSee('data:image/svg+xml;base64,', false);

    expect(InspectionAccessToken::query()->count())->toBe(1);
});

test('portal vehicle discovery links to authenticated inspection report', function () {
    $repairOrder = reportingRepairOrder();
    $customer = $repairOrder->customer;
    $vehicle = $repairOrder->vehicle;

    $this->actingAs($customer, 'portal')
        ->get(route('portal.vehicles.show', $vehicle))
        ->assertOk()
        ->assertSee('View inspection')
        ->assertSee(route('portal.vehicles.inspections.show', [
            'vehicle' => $vehicle,
            'repairOrder' => $repairOrder,
        ]), false);
});

test('internal photo is not deliverable on token or auth photo routes', function () {
    $repairOrder = reportingRepairOrder(withPhotos: true);
    $internal = InspectionItemPhoto::query()
        ->where('purpose', InspectionPhotoPurpose::Internal->value)
        ->firstOrFail();
    $plain = str_repeat('c', 64);
    InspectionAccessToken::createForPlainToken($repairOrder, $plain);

    $this->get(route('portal.inspections.photos.show', [
        'token' => $plain,
        'photo' => $internal,
    ]))->assertNotFound();

    $this->actingAs($repairOrder->customer, 'portal')
        ->get(route('portal.vehicles.inspections.photos.show', [
            'vehicle' => $repairOrder->vehicle,
            'repairOrder' => $repairOrder,
            'photo' => $internal,
        ]))
        ->assertNotFound();
});

test('staff preview still renders customer report', function () {
    $repairOrder = reportingRepairOrder();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.inspection-portal-preview', $repairOrder))
        ->assertOk()
        ->assertSee('Staff preview')
        ->assertSee('Vehicle Inspection')
        ->assertSee('LF brake pads');
});

test('qr helper encodes only the provided customer url', function () {
    $url = 'https://example.test/portal/inspections/'.str_repeat('d', 64);
    $uri = CustomerReportQrCode::svgDataUri($url);

    expect($uri)->toStartWith('data:image/svg+xml;base64,');
    $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));
    expect($svg)->toContain('<svg');
});

test('historical inspection without structured slots still projects measurements', function () {
    $repairOrder = reportingRepairOrder(recordStructured: false);
    $item = InspectionItem::query()->where('label', 'Legacy measurement')->firstOrFail();

    InspectionItemMeasurement::query()->create([
        'inspection_item_id' => $item->id,
        'name' => 'Pad thickness',
        'value' => '4',
        'unit' => 'mm',
        'position' => 0,
    ]);

    $report = app(InspectionReportProjection::class)->for($repairOrder->fresh(), InspectionReportProjection::MODE_DETAILED);
    $legacy = collect($report['points'])->firstWhere('label', 'Legacy measurement');

    expect($legacy['measurements'])->not->toBeEmpty()
        ->and($legacy['measurement_presentation']['kind'])->toBe('generic');
});

/**
 * @return RepairOrder
 */
function reportingRepairOrder(bool $withPhotos = false, bool $recordStructured = true): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Pat',
        'last_name' => 'Customer',
        'email' => 'pat.customer.'.uniqid().'@example.com',
        'phone' => '7195551212',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'repair_order_id' => random_int(9100, 9999),
        'concern_summary' => 'Brakes',
        'mileage_in' => 84210,
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes',
        'position' => 1,
    ]);

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, null);

    if ($recordStructured) {
        $brake = InspectionItem::query()->create([
            'inspection_id' => $inspection->id,
            'category' => 'brakes',
            'checklist_category_name' => 'Brakes',
            'label' => 'LF brake pads',
            'observed_state' => InspectionObservedState::Fail->value,
            'notes' => 'Pads worn thin.',
            'position' => 0,
        ]);

        InspectionItemMeasurement::query()->create([
            'inspection_item_id' => $brake->id,
            'name' => 'Inboard',
            'value' => '3.0',
            'unit' => 'mm',
            'position' => 0,
        ]);
        InspectionItemMeasurement::query()->create([
            'inspection_item_id' => $brake->id,
            'name' => 'Outboard',
            'value' => '7.0',
            'unit' => 'mm',
            'position' => 1,
        ]);

        $tire = InspectionItem::query()->create([
            'inspection_id' => $inspection->id,
            'category' => 'tires',
            'checklist_category_name' => 'Tires',
            'label' => 'LF tire tread',
            'observed_state' => InspectionObservedState::NeedsAttention->value,
            'notes' => 'Inner shoulder low.',
            'position' => 1,
        ]);

        foreach ([['Outer', '6'], ['Center', '6'], ['Inner', '3']] as $i => [$name, $value]) {
            InspectionItemMeasurement::query()->create([
                'inspection_item_id' => $tire->id,
                'name' => $name,
                'value' => $value,
                'unit' => '/32"',
                'position' => $i,
            ]);
        }

        InspectionItem::query()->create([
            'inspection_id' => $inspection->id,
            'category' => 'fluids',
            'checklist_category_name' => 'Fluids',
            'label' => 'Engine oil',
            'observed_state' => InspectionObservedState::Pass->value,
            'position' => 2,
        ]);

        InspectionItem::query()->create([
            'inspection_id' => $inspection->id,
            'category' => 'general',
            'checklist_category_name' => 'Road test',
            'label' => 'Road test',
            'observed_state' => InspectionObservedState::Na->value,
            'notes' => 'Not performed — vehicle unsafe to drive.',
            'position' => 3,
        ]);

        if ($withPhotos) {
            $actor = User::factory()->create();
            $customerPhoto = storeReportingPhoto($repairOrder->id, $brake->id, 'customer.jpg', InspectionPhotoPurpose::Customer, $actor->id);
            storeReportingPhoto($repairOrder->id, $brake->id, 'internal.jpg', InspectionPhotoPurpose::Internal, $actor->id);
            storeReportingVideo($repairOrder->id, $tire->id, 'clip.mp4', InspectionPhotoPurpose::Customer, $actor->id);
            expect($customerPhoto->id)->toBeInt();
        }
    } else {
        InspectionItem::query()->create([
            'inspection_id' => $inspection->id,
            'category' => 'brakes',
            'checklist_category_name' => 'Brakes',
            'label' => 'Legacy measurement',
            'observed_state' => InspectionObservedState::NeedsAttention->value,
            'position' => 0,
        ]);
    }

    return $repairOrder->fresh(['customer', 'vehicle', 'inspection.items.measurements', 'inspection.items.photos']);
}

function storeReportingPhoto(
    int $repairOrderId,
    int $itemId,
    string $name,
    InspectionPhotoPurpose $purpose,
    int $userId,
): InspectionItemPhoto {
    $path = 'inspection-photos/'.$repairOrderId.'/'.$name;
    $absolute = Storage::disk('local')->path($path);
    @mkdir(dirname($absolute), 0777, true);
    $image = imagecreatetruecolor(320, 240);
    imagejpeg($image, $absolute, 85);
    imagedestroy($image);

    return InspectionItemPhoto::query()->create([
        'inspection_item_id' => $itemId,
        'purpose' => $purpose->value,
        'storage_path' => $path,
        'content_type' => 'image/jpeg',
        'original_name' => $name,
        'byte_size' => (int) filesize($absolute),
        'uploaded_by_user_id' => $userId,
    ]);
}

function storeReportingVideo(
    int $repairOrderId,
    int $itemId,
    string $name,
    InspectionPhotoPurpose $purpose,
    int $userId,
): InspectionItemPhoto {
    $path = 'inspection-photos/'.$repairOrderId.'/'.$name;
    $absolute = Storage::disk('local')->path($path);
    @mkdir(dirname($absolute), 0777, true);
    file_put_contents($absolute, 'fake-video');

    return InspectionItemPhoto::query()->create([
        'inspection_item_id' => $itemId,
        'purpose' => $purpose->value,
        'storage_path' => $path,
        'content_type' => 'video/mp4',
        'original_name' => $name,
        'byte_size' => (int) filesize($absolute),
        'uploaded_by_user_id' => $userId,
    ]);
}
