<?php

use App\Ark\Operations\RepairOrders\RepairOrderFooterProjection;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderWorkspaceStripProjection;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('workspace strip is identity-only; footer owns add work for advisors', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $repairOrder = repairOrderForEstimateWorkspace();

    $strip = RepairOrderWorkspaceStripProjection::for($repairOrder, 'presentation', $advisor);
    $footer = RepairOrderFooterProjection::for($repairOrder, $advisor);

    expect($strip->mode)->toBe('presentation')
        ->and($strip->primaryAction->key)->toBe('none')
        ->and($footer->workflow->key)->toBe('add_work')
        ->and($footer->workflow->label)->toBe('+ Add Work')
        ->and($footer->workflow->opensModal)->toBeTrue();
});

test('footer presents customer display; tablet stays hidden until customer Flutter surface exists', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    ShopSettings::current()->update([
        'qz_printing_enabled' => true,
    ]);
    ShopSettings::forgetCurrent();

    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = concernForEstimateWorkspace($repairOrder);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Diagnostic labor',
        'quantity' => '1.00',
        'unit_price_cents' => 12000,
        'total_cents' => 12000,
    ]);

    $footer = RepairOrderFooterProjection::for($repairOrder->fresh(['lines']), $advisor);
    $presentKeys = collect($footer->present)->pluck('key')->all();
    $utilityKeys = collect($footer->utilities)->pluck('key')->all();
    $presentHrefs = collect($footer->present)->pluck('href')->filter()->implode(' ');

    expect($presentKeys)->toContain('paperwork')
        ->and($presentKeys)->toContain('customer_display')
        ->and($presentHrefs)->toContain('/customer-display')
        ->and($presentHrefs)->not->toContain('portal-preview')
        ->and($presentKeys)->not->toContain('tablet')
        ->and($presentHrefs)->not->toContain('surface=tablet')
        ->and($presentHrefs)->not->toContain('/inspection')
        ->and($utilityKeys)->toContain('estimate_pdf')
        ->and($utilityKeys)->toContain('key_tag')
        ->and($utilityKeys)->toContain('oil_sticker');

    $paperwork = collect($footer->present)->firstWhere('key', 'paperwork');
    expect($paperwork)->not->toBeNull()
        ->and($paperwork->opensModal)->toBeTrue()
        ->and($paperwork->modalTask)->toBe('document')
        ->and($paperwork->label)->toBe('+ Add Document');
});

test('workspace strip projection offers continue inspection on inspect mode', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $repairOrder = repairOrderForEstimateWorkspace();
    $repairOrder->forceFill(['assigned_technician_id' => $technician->id])->save();

    $strip = RepairOrderWorkspaceStripProjection::for($repairOrder->fresh(), 'inspect', $technician);

    expect($strip->mode)->toBe('inspect')
        ->and($strip->primaryAction->key)->toBe('open_inspection')
        ->and($strip->primaryAction->label)->toBe('Open Inspection')
        ->and($strip->primaryAction->opensInNewTab)->toBeTrue();
});

test('canonical repair order dock is footer-first without posture dashboard', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForEstimateWorkspace();

    $response = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('data-ro-footer', false)
        ->assertSee('+ Add Work', false)
        ->assertSee('+ Add Document', false)
        ->assertSee('PRINT', false)
        ->assertSee('ops-ro-orientation-header--footer-first', false)
        ->assertSee('ops-ro-footer__top', false)
        ->assertDontSee('Waiting on Diagnosis', false)
        ->assertDontSee('data-posture-layout="dock"', false)
        ->assertDontSee('>Editing<', false)
        ->assertDontSee('>Viewing<', false)
        ->assertDontSee('id="builder-add-work"', false);

    // Persistent Context posture remains on the right rail — not the footer dock.
    expect($response->getContent())->toContain('data-posture-layout="rail"');
});

test('legacy edit builder and estimate-review urls redirect to canonical show', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForEstimateWorkspace();

    $this->actingAs($advisor)
        ->get('/app/repair-orders/'.$repairOrder->repair_order_id.'/edit')
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder));

    $this->actingAs($advisor)
        ->get('/app/repair-orders/'.$repairOrder->repair_order_id.'/builder')
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder));

    $this->actingAs($advisor)
        ->get('/app/repair-orders/'.$repairOrder->repair_order_id.'/estimate-review')
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder));
});

test('workspace strip projection includes vin when present', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $repairOrder = repairOrderForEstimateWorkspace();
    $repairOrder->vehicle->update(['vin' => '1HGBH41JXMN109186']);

    $strip = RepairOrderWorkspaceStripProjection::for($repairOrder->fresh(['vehicle', 'customer']), 'presentation', $advisor);

    expect($strip->vin)->toBe('1HGBH41JXMN109186')
        ->and($strip->customerLabel)->not->toBe('')
        ->and($strip->vehicleLabel)->not->toBe('');
});

test('print dropdown still renders on the canonical repair order', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = repairOrderForEstimateWorkspace();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('ops-review-print-menu', false)
        ->assertSee('>PRINT<', false)
        ->assertSee('Check In sheet', false)
        ->assertSee('Estimate PDF', false);
});
