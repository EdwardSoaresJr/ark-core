<?php

use App\Ark\Operations\Contribution\ContributeEvidence;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionItemPhoto;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Inspections\InspectionPhotoPurpose;
use App\Ark\Operations\Leads\Public\CommonProblemFeaturedMedia;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');
    Storage::fake('public');
});

function contributeEvidenceRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Contribute',
        'last_name' => 'Customer',
        'phone' => '7195552000',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'CONT1',
        'year' => 2018,
        'make' => 'Toyota',
        'model' => 'Camry',
        'vin' => '4T1B11HK5JU123456',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Brake noise.',
    ]);
}

function contributeEvidencePhoto(RepairOrder $repairOrder, User $actor): InspectionItemPhoto
{
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $actor);

    $item = $inspection->items()->create([
        'category' => 'brakes',
        'label' => 'Front brake pads',
        'observed_state' => InspectionObservedState::Fail->value,
        'position' => 0,
    ]);

    $path = 'inspection-photos/'.$repairOrder->id.'/pad.jpg';
    $absolute = Storage::disk('local')->path($path);
    @mkdir(dirname($absolute), 0777, true);
    $image = imagecreatetruecolor(640, 480);
    imagejpeg($image, $absolute, 85);
    imagedestroy($image);

    return InspectionItemPhoto::query()->create([
        'inspection_item_id' => $item->id,
        'purpose' => InspectionPhotoPurpose::Customer->value,
        'storage_path' => $path,
        'content_type' => 'image/jpeg',
        'original_name' => 'pad.jpg',
        'byte_size' => (int) filesize($absolute),
        'uploaded_by_user_id' => $actor->id,
    ]);
}

test('contribute evidence succeeds and stores traceability', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $repairOrder = contributeEvidenceRepairOrder();
    $photo = contributeEvidencePhoto($repairOrder, $advisor);

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.inspection.show', $repairOrder))
        ->post(route('operations.repair-orders.inspection.photos.contribute', [$repairOrder, $photo]), [
            'common_problem_slug' => 'brake-noise',
            'caption' => 'Pad wear measured before recommendation.',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $gallery = CommonProblemFeaturedMedia::forSlug('brake-noise');

    expect($gallery)->toHaveCount(1)
        ->and($gallery[0]['source_repair_order_id'])->toBe($repairOrder->id)
        ->and($gallery[0]['source_media_id'])->toBe($photo->id)
        ->and($gallery[0]['contributed_by'])->toBe($advisor->id)
        ->and($gallery[0]['contributed_at'])->not->toBeEmpty()
        ->and($gallery[0]['caption'])->toBe('Pad wear measured before recommendation.');

    $this->get(route('public.common-problems.show', 'brake-noise'))
        ->assertOk()
        ->assertSee('public-featured-media', false);
});

test('contribute evidence rejects missing photo file', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $repairOrder = contributeEvidenceRepairOrder();
    $photo = contributeEvidencePhoto($repairOrder, $advisor);
    Storage::disk('local')->delete($photo->storage_path);

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.inspection.photos.contribute', [$repairOrder, $photo]), [
            'common_problem_slug' => 'brake-noise',
        ])
        ->assertSessionHasErrors('inspection_photo_id');

    expect(CommonProblemFeaturedMedia::forSlug('brake-noise'))->toBe([]);
});

test('contribute evidence rejects missing slug', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $repairOrder = contributeEvidenceRepairOrder();
    $photo = contributeEvidencePhoto($repairOrder, $advisor);

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.inspection.photos.contribute', [$repairOrder, $photo]), [
            'common_problem_slug' => '',
        ])
        ->assertSessionHasErrors('common_problem_slug');

    expect(CommonProblemFeaturedMedia::forSlug('brake-noise'))->toBe([]);
});

test('existing featured media gallery still renders after contribution', function (): void {
    $path = CommonProblemFeaturedMedia::STORAGE_PREFIX.'brake-noise/existing.jpg';
    Storage::disk('public')->put($path, 'fake-image');

    CommonProblemFeaturedMedia::persistGalleryForSlug('brake-noise', [[
        'id' => 'existing',
        'path' => $path,
        'alt' => 'Technician measuring brake pad thickness on a Colorado Springs vehicle',
        'caption' => 'Existing gallery photo.',
    ]]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $repairOrder = contributeEvidenceRepairOrder();
    $photo = contributeEvidencePhoto($repairOrder, $advisor);

    app(ContributeEvidence::class)->contributeInspectionPhoto(
        $repairOrder,
        $photo,
        'brake-noise',
        $advisor,
        'Contributed inspection photo.',
    );

    expect(CommonProblemFeaturedMedia::forSlug('brake-noise'))->toHaveCount(2);

    $this->get(route('public.common-problems.show', 'brake-noise'))
        ->assertOk()
        ->assertSee('public-featured-media', false)
        ->assertSee('Technician measuring brake pad thickness on a Colorado Springs vehicle', false);
});

test('contribute evidence rejects unknown slug via action', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $repairOrder = contributeEvidenceRepairOrder();
    $photo = contributeEvidencePhoto($repairOrder, $advisor);

    expect(fn () => app(ContributeEvidence::class)->contributeInspectionPhoto(
        $repairOrder,
        $photo,
        'not-a-real-page',
        $advisor,
    ))->toThrow(ValidationException::class);
});
