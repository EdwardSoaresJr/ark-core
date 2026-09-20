<?php

use App\Ark\Operations\Evidence\AttachEvidenceAction;
use App\Ark\Operations\Evidence\ChangeEvidenceVisibilityAction;
use App\Ark\Operations\Evidence\Evidence;
use App\Ark\Operations\Evidence\EvidenceAttachment;
use App\Ark\Operations\Evidence\EvidenceSource;
use App\Ark\Operations\Evidence\EvidenceVisibility;
use App\Ark\Operations\Evidence\EvidenceVisibilityHistory;
use App\Ark\Operations\Evidence\RecordEvidenceCustomerPresentedAction;
use App\Ark\Operations\Evidence\RetireEvidenceAction;
use App\Ark\Operations\Evidence\SetPrimaryEvidenceAction;
use App\Ark\Operations\Portal\CreateOrReuseEstimateAccessTokenAction;
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

function attachConcernEvidence($repairOrder, $actor, ?RepairOrderConcern $concern = null, bool $asPrimary = false): Evidence
{
    $concern ??= RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Grinding while braking',
        'position' => 1,
    ]);

    return app(AttachEvidenceAction::class)->handle(
        $repairOrder,
        $concern,
        UploadedFile::fake()->image('rotor.jpg'),
        $actor,
        EvidenceSource::Upload,
        'Inner rotor lip',
        $asPrimary,
    );
}

test('attach evidence defaults internal and writes visibility history', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $repairOrder = repairOrderForEstimateWorkspace();

    $evidence = attachConcernEvidence($repairOrder, $admin);

    $history = EvidenceVisibilityHistory::query()->where('evidence_id', $evidence->id)->first();

    expect($evidence->visibility)->toBe(EvidenceVisibility::Internal)
        ->and($evidence->storage_path)->not->toBe('')
        ->and($history)->not->toBeNull()
        ->and($history?->new_visibility)->toBe(EvidenceVisibility::Internal);

    Storage::disk('local')->assertExists($evidence->storage_path);
});

test('show to customer audits visibility and sets shared_at', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $repairOrder = repairOrderForEstimateWorkspace();
    $evidence = attachConcernEvidence($repairOrder, $admin);

    $updated = app(ChangeEvidenceVisibilityAction::class)->handle(
        $evidence,
        EvidenceVisibility::Shared,
        $admin,
    );

    expect($updated->visibility)->toBe(EvidenceVisibility::Shared)
        ->and($updated->shared_at)->not->toBeNull()
        ->and(EvidenceVisibilityHistory::query()->where('evidence_id', $evidence->id)->count())->toBe(2);
});

test('shared media stream authorizes without recording customer viewed', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $repairOrder = repairOrderForEstimateWorkspace();
    $evidence = attachConcernEvidence($repairOrder, $admin);
    app(ChangeEvidenceVisibilityAction::class)->handle($evidence, EvidenceVisibility::Shared, $admin);

    $access = app(CreateOrReuseEstimateAccessTokenAction::class)->execute($repairOrder);

    $this->get(route('portal.estimates.evidence.show', [
        'token' => $access->plainToken,
        'evidence' => $evidence->id,
    ]))->assertOk();

    expect($evidence->fresh()->first_customer_viewed_at)->toBeNull();
});

test('portal media stream 404s for internal evidence', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $repairOrder = repairOrderForEstimateWorkspace();
    $evidence = attachConcernEvidence($repairOrder, $admin);
    $access = app(CreateOrReuseEstimateAccessTokenAction::class)->execute($repairOrder);

    $this->get(route('portal.estimates.evidence.show', [
        'token' => $access->plainToken,
        'evidence' => $evidence->id,
    ]))->assertNotFound();
});

test('set primary is transactional and rejects retired', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Grinding',
        'position' => 1,
    ]);

    $first = attachConcernEvidence($repairOrder, $admin, $concern, asPrimary: true);
    $second = attachConcernEvidence($repairOrder, $admin, $concern);

    $secondAttachment = EvidenceAttachment::query()->where('evidence_id', $second->id)->firstOrFail();
    app(SetPrimaryEvidenceAction::class)->handle($repairOrder, $secondAttachment, $admin);

    expect(EvidenceAttachment::query()->where('evidence_id', $first->id)->value('is_primary'))->toBeFalse()
        ->and(EvidenceAttachment::query()->where('evidence_id', $second->id)->value('is_primary'))->toBeTrue();

    app(RetireEvidenceAction::class)->handle($repairOrder, $second);
    $retiredAttachment = EvidenceAttachment::query()->where('evidence_id', $second->id)->firstOrFail();

    expect(fn () => app(SetPrimaryEvidenceAction::class)->handle($repairOrder, $retiredAttachment, $admin))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    Storage::disk('local')->assertExists($second->storage_path);
    expect($second->fresh()->trashed())->toBeTrue();
});

test('ro boundary rejects cross repair order attachable', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $a = repairOrderForEstimateWorkspace();
    $b = repairOrderForEstimateWorkspace();
    $foreignConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $b->id,
        'summary' => 'Other RO',
        'position' => 1,
    ]);

    expect(fn () => app(AttachEvidenceAction::class)->handle(
        $a,
        $foreignConcern,
        UploadedFile::fake()->image('x.jpg'),
        $admin,
    ))->toThrow(\InvalidArgumentException::class);
});

test('presentation action sets first_customer_viewed_at once', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $repairOrder = repairOrderForEstimateWorkspace();
    $evidence = attachConcernEvidence($repairOrder, $admin);
    app(ChangeEvidenceVisibilityAction::class)->handle($evidence, EvidenceVisibility::Shared, $admin);

    app(RecordEvidenceCustomerPresentedAction::class)->handle($repairOrder, collect([$evidence->fresh()]));
    $first = $evidence->fresh()->first_customer_viewed_at;
    expect($first)->not->toBeNull();

    app(RecordEvidenceCustomerPresentedAction::class)->handle($repairOrder, collect([$evidence->fresh()]));
    expect($evidence->fresh()->first_customer_viewed_at?->equalTo($first))->toBeTrue();
});

test('staff can stream active evidence', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $repairOrder = repairOrderForEstimateWorkspace();
    $evidence = attachConcernEvidence($repairOrder, $admin);

    $this->actingAs($admin)
        ->get(route('operations.repair-orders.evidence.show', [$repairOrder, $evidence]))
        ->assertOk();
});
