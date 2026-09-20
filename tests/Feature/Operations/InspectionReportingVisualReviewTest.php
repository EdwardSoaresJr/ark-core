<?php

/**
 * Renders Inspection Reporting v1 HTML fixtures for visual STOP review.
 * Run: INSPECTION_REPORT_VISUAL=1 php artisan test --filter=InspectionReportingVisualReviewTest
 *
 * Open files under storage/app/inspection-report-review/
 */

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\AssignRepairOrderInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionItemMeasurement;
use App\Ark\Operations\Inspections\InspectionItemPhoto;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Inspections\InspectionPhotoPurpose;
use App\Ark\Operations\Inspections\InspectionReportProjection;
use App\Ark\Operations\Portal\CreateOrReuseInspectionAccessTokenAction;
use App\Ark\Operations\Portal\PortalInspectionPage;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    if (! env('INSPECTION_REPORT_VISUAL')) {
        $this->markTestSkipped('Set INSPECTION_REPORT_VISUAL=1 to render review fixtures.');
    }

    $this->seed(ArkAuthorizationSeeder::class);
    DefaultInspectionTemplateCatalog::seedIfMissing();
});

test('render inspection reporting visual review fixtures', function () {
    $dir = storage_path('app/inspection-report-review');
    File::ensureDirectoryExists($dir);

    $standard = visualReviewRepairOrder(template: 'standard');
    $ppi = visualReviewRepairOrder(template: 'ppi');

    $page = app(PortalInspectionPage::class);
    $tokenStandard = app(CreateOrReuseInspectionAccessTokenAction::class)->execute($standard);
    $tokenPpi = app(CreateOrReuseInspectionAccessTokenAction::class)->execute($ppi);

    $fixtures = [
        '01-standard-simple-portal.html' => $page->renderToken($tokenStandard->token, $tokenStandard->plainToken, false, false, InspectionReportProjection::MODE_SIMPLE)->render(),
        '02-standard-detailed-portal.html' => $page->renderToken($tokenStandard->token, $tokenStandard->plainToken, false, false, InspectionReportProjection::MODE_DETAILED)->render(),
        '03-ppi-simple-portal.html' => $page->renderToken($tokenPpi->token, $tokenPpi->plainToken, false, false, InspectionReportProjection::MODE_SIMPLE)->render(),
        '04-ppi-detailed-portal.html' => $page->renderToken($tokenPpi->token, $tokenPpi->plainToken, false, false, InspectionReportProjection::MODE_DETAILED)->render(),
        '05-standard-simple-print.html' => $page->printHtml($standard, InspectionReportProjection::MODE_SIMPLE, route('portal.inspections.show', ['token' => $tokenStandard->plainToken]), $tokenStandard->plainToken),
        '06-ppi-detailed-print.html' => $page->printHtml($ppi, InspectionReportProjection::MODE_DETAILED, route('portal.inspections.show', ['token' => $tokenPpi->plainToken]), $tokenPpi->plainToken),
    ];

    foreach ($fixtures as $name => $html) {
        File::put($dir.'/'.$name, $html);
        expect(File::exists($dir.'/'.$name))->toBeTrue();
    }

    File::put($dir.'/README.txt', implode("\n", [
        'Inspection Reporting v1 — visual STOP review',
        'Open each HTML file in a browser.',
        'Print/PDF fixtures are chrome-free (05, 06). Use browser Print → PDF for PDF look.',
        'Portal fixtures (01–04) include customer shell chrome.',
        '',
        ...array_keys($fixtures),
    ]));
});

function visualReviewRepairOrder(string $template): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Alex',
        'last_name' => 'Driver',
        'email' => 'alex.driver.'.uniqid().'@example.com',
        'phone' => '7195554242',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'Toyota',
        'model' => '4Runner',
    ]);

    $standard = DefaultInspectionTemplateCatalog::standardTemplate();

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'repair_order_id' => random_int(8000, 8999),
        'concern_summary' => 'Inspection review sample',
        'mileage_in' => 118442,
        'required_inspection_template_id' => $standard?->id,
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Inspection review sample',
        'position' => 1,
    ]);

    if ($template === 'ppi') {
        $ppi = DefaultInspectionTemplateCatalog::ppiTemplate();
        app(AssignRepairOrderInspectionTemplateAction::class)->execute($repairOrder, $ppi);
    }

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder->fresh(), null);
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder->fresh(), $inspection, actor: null);
    $inspection = $inspection->fresh(['items']);
    $actor = User::factory()->create();

    $items = $inspection->items()->orderBy('position')->orderBy('id')->get();
    expect($items)->not->toBeEmpty();

    $recorded = 0;
    foreach ($items as $index => $item) {
        if ($recorded >= 12) {
            break;
        }

        $state = match ($index % 5) {
            0 => InspectionObservedState::Fail,
            1 => InspectionObservedState::NeedsAttention,
            2 => InspectionObservedState::Monitor,
            3 => InspectionObservedState::Pass,
            default => InspectionObservedState::Na,
        };

        $item->forceFill([
            'observed_state' => $state->value,
            'notes' => $state === InspectionObservedState::Na
                ? 'Not performed for this visit.'
                : 'Technician note for visual review.',
        ])->save();

        if (str_contains(strtolower($item->label), 'pad') || str_contains(strtolower((string) $item->checklist_category_name), 'brake')) {
            InspectionItemMeasurement::query()->create([
                'inspection_item_id' => $item->id,
                'name' => 'Inboard',
                'value' => '3.0',
                'unit' => 'mm',
                'position' => 0,
            ]);
            InspectionItemMeasurement::query()->create([
                'inspection_item_id' => $item->id,
                'name' => 'Outboard',
                'value' => '7.0',
                'unit' => 'mm',
                'position' => 1,
            ]);
        }

        if (str_contains(strtolower($item->label), 'tire') || str_contains(strtolower($item->label), 'tread')) {
            foreach ([['Outer', '6'], ['Center', '6'], ['Inner', '3']] as $i => [$name, $value]) {
                InspectionItemMeasurement::query()->create([
                    'inspection_item_id' => $item->id,
                    'name' => $name,
                    'value' => $value,
                    'unit' => '/32"',
                    'position' => $i,
                ]);
            }
        }

        if (in_array($state, [InspectionObservedState::Fail, InspectionObservedState::NeedsAttention], true)) {
            $path = 'inspection-photos/'.$repairOrder->id.'/review-'.$item->id.'.jpg';
            $absolute = Storage::disk('local')->path($path);
            @mkdir(dirname($absolute), 0777, true);
            $image = imagecreatetruecolor(640, 480);
            $color = imagecolorallocate($image, 30 + ($index * 20) % 200, 80, 120);
            imagefilledrectangle($image, 0, 0, 640, 480, $color);
            imagejpeg($image, $absolute, 85);
            imagedestroy($image);

            InspectionItemPhoto::query()->create([
                'inspection_item_id' => $item->id,
                'purpose' => InspectionPhotoPurpose::Customer->value,
                'storage_path' => $path,
                'content_type' => 'image/jpeg',
                'original_name' => 'review-'.$item->id.'.jpg',
                'byte_size' => (int) filesize($absolute),
                'uploaded_by_user_id' => $actor->id,
            ]);
        }

        if ($index === 1) {
            $vpath = 'inspection-photos/'.$repairOrder->id.'/review-'.$item->id.'.mp4';
            $vabsolute = Storage::disk('local')->path($vpath);
            file_put_contents($vabsolute, 'fake-video-for-review');
            InspectionItemPhoto::query()->create([
                'inspection_item_id' => $item->id,
                'purpose' => InspectionPhotoPurpose::Customer->value,
                'storage_path' => $vpath,
                'content_type' => 'video/mp4',
                'original_name' => 'review-'.$item->id.'.mp4',
                'byte_size' => (int) filesize($vabsolute),
                'uploaded_by_user_id' => $actor->id,
            ]);
        }

        $recorded++;
    }

    return $repairOrder->fresh(['customer', 'vehicle', 'inspection.items.measurements', 'inspection.items.photos']);
}
