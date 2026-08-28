<?php

use App\Ark\Operations\Documents\DocumentPdfPresenter;
use App\Ark\Operations\Documents\EstimateDocumentPdfSnapshot;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Evidence\AttachEvidenceAction;
use App\Ark\Operations\Evidence\ChangeEvidenceVisibilityAction;
use App\Ark\Operations\Evidence\EvidenceSource;
use App\Ark\Operations\Evidence\EvidenceVisibility;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Portal\CreateOrReuseRepairOrderPortalAccessAction;
use App\Ark\Operations\Portal\RepairOrderPortalAccess;
use App\Ark\Operations\Portal\RepairPortalAdvertisementProjection;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->seed(ShopSettingsSeeder::class);
    Storage::fake('local');
});

test('create or reuse returns same public code and never mutates it', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $repairOrder = repairOrderForEstimateWorkspace();
    $action = app(CreateOrReuseRepairOrderPortalAccessAction::class);

    $first = $action->execute($repairOrder, $admin);
    $second = $action->execute($repairOrder, $admin);

    expect($second->id)->toBe($first->id)
        ->and($second->public_code)->toBe($first->public_code)
        ->and(RepairOrderPortalAccess::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1);
});

test('revoke and replace appends a new code without mutating the old row', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $repairOrder = repairOrderForEstimateWorkspace();
    $action = app(CreateOrReuseRepairOrderPortalAccessAction::class);

    $first = $action->execute($repairOrder, $admin);
    $oldCode = $first->public_code;

    $second = $action->revokeAndReplace($repairOrder, $admin);

    expect($second->id)->not->toBe($first->id)
        ->and($second->public_code)->not->toBe($oldCode)
        ->and($first->fresh()->revoked_at)->not->toBeNull()
        ->and($first->fresh()->public_code)->toBe($oldCode)
        ->and(RepairOrderPortalAccess::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(2);

    $this->get(route('portal.repair.show', ['code' => $oldCode]))->assertNotFound();
    $this->get(route('portal.repair.show', ['code' => $second->public_code]))->assertOk();
});

test('hub shows shared evidence and hides internal', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Grinding while braking',
        'position' => 1,
    ]);

    $internal = app(AttachEvidenceAction::class)->handle(
        $repairOrder,
        $concern,
        UploadedFile::fake()->image('internal.jpg'),
        $admin,
        EvidenceSource::Upload,
        'Internal only',
    );
    $shared = app(AttachEvidenceAction::class)->handle(
        $repairOrder,
        $concern,
        UploadedFile::fake()->image('shared.jpg'),
        $admin,
        EvidenceSource::Upload,
        'Torn outer CV boot',
    );
    app(ChangeEvidenceVisibilityAction::class)->handle($shared, EvidenceVisibility::Shared, $admin);

    $access = app(CreateOrReuseRepairOrderPortalAccessAction::class)->execute($repairOrder, $admin);

    auth()->logout();

    $this->get(route('portal.repair.show', ['code' => $access->public_code]))
        ->assertOk()
        ->assertSee('Your vehicle online', false)
        ->assertSee('Torn outer CV boot', false)
        ->assertDontSee('Internal only', false);

    $this->get(route('portal.repair.evidence.show', [
        'code' => $access->public_code,
        'evidence' => $shared->id,
    ]))->assertOk();

    $this->get(route('portal.repair.evidence.show', [
        'code' => $access->public_code,
        'evidence' => $internal->id,
    ]))->assertNotFound();
});

test('estimate pdf snapshot includes repair portal advertisement with qr', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $repairOrder = repairOrderForEstimateWorkspace();

    $document = app(EstimateDocumentService::class)->createOrRefresh($repairOrder, $admin);
    $snapshot = app(DocumentPdfPresenter::class)->prepareForCustomer(
        app(EstimateDocumentPdfSnapshot::class)->resolve($document, $admin),
    );

    expect($snapshot['repair_portal'] ?? null)->toBeArray()
        ->and($snapshot['repair_portal']['cta'])->toBe('View your vehicle online')
        ->and($snapshot['repair_portal']['qr_data_uri'])->toStartWith('data:image/svg+xml;base64,')
        ->and($snapshot['repair_portal']['url'])->toContain('/r/')
        ->and(RepairOrderPortalAccess::query()->where('repair_order_id', $repairOrder->id)->active()->count())->toBe(1);
});

test('hub links to inspection report when findings exist', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $repairOrder = repairOrderForEstimateWorkspace();

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $admin);
    InspectionItem::query()->create([
        'inspection_id' => $inspection->id,
        'category' => 'general',
        'checklist_category_name' => 'General',
        'label' => 'Road test',
        'observed_state' => InspectionObservedState::NeedsAttention->value,
        'notes' => 'Pulls right',
        'position' => 0,
    ]);

    $access = app(CreateOrReuseRepairOrderPortalAccessAction::class)->execute($repairOrder, $admin);

    auth()->logout();

    $this->get(route('portal.repair.show', ['code' => $access->public_code]))
        ->assertOk()
        ->assertSee('View inspection report', false)
        ->assertSee(route('portal.repair.inspection.show', ['code' => $access->public_code]), false);

    $this->get(route('portal.repair.inspection.show', ['code' => $access->public_code]))
        ->assertOk()
        ->assertSee('Vehicle Inspection')
        ->assertSee('Road test');
});

test('advertisement bullets reflect shared evidence counts', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes',
        'position' => 1,
    ]);

    $photo = app(AttachEvidenceAction::class)->handle(
        $repairOrder,
        $concern,
        UploadedFile::fake()->image('pad.jpg'),
        $admin,
    );
    app(ChangeEvidenceVisibilityAction::class)->handle($photo, EvidenceVisibility::Shared, $admin);

    $ad = app(RepairPortalAdvertisementProjection::class)->forRepairOrder($repairOrder, $admin);

    expect($ad['has_shared_evidence'])->toBeTrue()
        ->and($ad['photo_count'])->toBe(1)
        ->and($ad['bullets'])->toContain('1 Photo');
});
