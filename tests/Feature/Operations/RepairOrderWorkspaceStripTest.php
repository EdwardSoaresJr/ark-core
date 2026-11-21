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
        ->assertSee('data-ro-orientation-header', false)
        ->assertSee('data-ro-footer-top', false)
        ->assertDontSee('Waiting on Diagnosis', false)
        ->assertDontSee('data-posture-layout="dock"', false)
        ->assertDontSee('data-posture-layout="rail"', false)
        ->assertDontSee('>Editing<', false)
        ->assertDontSee('>Viewing<', false)
        ->assertDontSee('id="builder-add-work"', false);
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
        ->assertSee('data-ro-print-menu', false)
        ->assertSee('data-bs-toggle="dropdown"', false)
        ->assertSee('Check In sheet', false)
        ->assertSee('Estimate PDF', false);
});

/**
 * @return array{0: \DOMXPath, 1: \DOMElement}
 */
function roDockSummary(string $html): array
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $summary = $xpath->query('//*[@id="ro-orientation-header"]//*[@data-ro-dock-summary]')->item(0);

    expect($summary)->toBeInstanceOf(DOMElement::class);

    return [$xpath, $summary];
}

test('the dock summary reads RO number, VIN, customer, vehicle with the VIN as the only copy control', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForEstimateWorkspace();
    $repairOrder->vehicle->forceFill(['vin' => '1hgcm82633a004352', 'normalized_vin' => '1HGCM82633A004352'])->save();

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();

    [$xpath, $summary] = roDockSummary($html);
    $text = preg_replace('/\s+/', ' ', $summary->textContent);

    $ro = strpos($text, 'RO');
    $vin = strpos($text, '1HGCM82633A004352');
    $customer = strpos($text, 'Rosa');
    $vehicle = strpos($text, 'Honda');

    expect($ro)->not->toBeFalse()
        ->and($vin)->toBeGreaterThan($ro)
        ->and($customer)->toBeGreaterThan($vin)
        ->and($vehicle)->toBeGreaterThan($customer);

    $buttons = $xpath->query('.//button[@data-vin-copy]', $summary);
    expect($buttons->length)->toBe(1)
        ->and(trim($buttons->item(0)->textContent))->toBe('1HGCM82633A004352')
        ->and($xpath->query('.//button[not(ancestor::template)]', $summary)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-ro-dock-vin-missing]', $summary)->length)->toBe(0)
        ->and($summary->getElementsByTagName('a')->length)->toBe(0);

    // Phonetic readback: one entry per character, served without hover, from the vehicle's own VIN.
    $items = $xpath->query('//*[@data-vin-phonetic-list]/li');
    $spelled = collect($items)->map(fn (DOMElement $li): string => preg_replace('/\s+/', ' ', trim($li->textContent)))->all();

    expect($items->length)->toBe(17)
        ->and($spelled[0])->toBe('1 — One')
        ->and($spelled[1])->toBe('H — Hotel')
        ->and($spelled[2])->toBe('G — Golf')
        ->and($spelled[9])->toBe('3 — Three')
        ->and($spelled[10])->toBe('A — Alpha')
        ->and($spelled[16])->toBe('2 — Two');
});

test('the dock summary shows a non-clickable VIN unavailable state when the vehicle has no VIN', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForEstimateWorkspace();

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();

    [$xpath, $summary] = roDockSummary($html);
    $missing = $xpath->query('.//*[@data-ro-dock-vin-missing]', $summary);

    expect($missing->length)->toBe(1)
        ->and(trim($missing->item(0)->textContent))->toBe('VIN unavailable')
        ->and($missing->item(0)->nodeName)->toBe('span')
        ->and($xpath->query('.//button[not(ancestor::template)]', $summary)->length)->toBe(0)
        ->and($xpath->query('//*[@data-vin-copy]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-vin-phonetic-list]')->length)->toBe(0);
});
