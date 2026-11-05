<?php

use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
});

function repairOrderHeaderXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

function repairOrderHeaderHasClass(string $class): string
{
    return 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
}

test('the repair order page begins with the Customer, Vehicle, and Visit panel, and the RO number heads the Visit panel', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForEstimateWorkspace();
    $roNumber = 'RO #'.$repairOrder->repair_order_id;

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();
    $xpath = repairOrderHeaderXpath($html);
    $visit = $xpath->query('//*[@id="ro-identity-band"]//section[@data-identity-present="visit"]')->item(0);

    expect($xpath->query('//*['.repairOrderHeaderHasClass('page-header').']')->length)->toBe(0)
        ->and($xpath->query('//*['.repairOrderHeaderHasClass('page-pretitle').']')->length)->toBe(0)
        ->and($visit)->toBeInstanceOf(DOMElement::class)
        ->and(trim($xpath->query('.//h2[@data-ro-number]', $visit)->item(0)->textContent))->toBe($roNumber)
        ->and($xpath->query('//h2[normalize-space()="'.$roNumber.'"]')->length)->toBe(1)
        ->and($xpath->query('.//*[@id="review-toolbar"]', $visit)->length)->toBe(1)
        ->and($xpath->query('.//dt[normalize-space()="Visit type"]', $visit)->length)->toBe(1)
        ->and($xpath->query('.//dt[normalize-space()="Status"]', $visit)->length)->toBe(0);
});

test('the Visit panel keeps the labeled status and technician selects, Review Estimate Notes, and the print menu; paperwork stays in the footer', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create(['name' => 'Bay Technician'])->assignRole(ArkRole::Technician->value);
    $repairOrder = repairOrderForEstimateWorkspace();
    $id = $repairOrder->repair_order_id;

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();
    $xpath = repairOrderHeaderXpath($html);
    $visit = $xpath->query('//section[@data-identity-present="visit"]')->item(0);
    $bar = $xpath->query('.//*[@id="review-toolbar"]', $visit)->item(0);
    $actions = $xpath->query('.//*[@data-ro-command-actions]', $visit)->item(0);

    expect($xpath->query('.//*[@data-ro-command-workflow]//form[@id="repair-order-workflow"][@data-lifecycle-form][@action="'.route('operations.repair-orders.lifecycle.update', $repairOrder).'"]//select[@id="status-move-'.$id.'"][@data-lifecycle-select]', $bar)->length)->toBe(1)
        ->and(trim($xpath->query('.//label[@for="status-move-'.$id.'"]', $bar)->item(0)->textContent))->toBe('Status')
        ->and($xpath->query('.//*['.repairOrderHeaderHasClass('ark-ro-visit-commands__status').']//*[@data-lost-reason-panel][@hidden]', $bar)->length)->toBe(1)
        ->and(trim($xpath->query('.//label[@for="assigned-technician-toolbar-'.$id.'"]', $bar)->item(0)->textContent))->toBe('Technician')
        ->and($xpath->query('.//select[@id="assigned-technician-toolbar-'.$id.'"]/option[@value="'.$technician->id.'"]', $bar)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-ro-mode-toolbar-slot]', $bar)->length)->toBe(1)
        ->and($actions)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('.//button[@aria-label="Review Estimate Notes"]', $actions)->length)->toBe(1)
        ->and(preg_match('/aria-label="Review Estimate Notes"\s+@click="[^"]*task: \'review-estimate-notes\'/', $html))->toBe(1)
        ->and($xpath->query('.//*[@data-ro-print-menu]//button[@data-bs-toggle="dropdown"][@aria-label="Print"]', $actions)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-ro-print-menu]//*[normalize-space()="Estimate PDF"]', $actions)->length)->toBe(1)
        ->and($xpath->query('//*[@id="ro-identity-band"]//*[@data-workspace-modal-trigger="document"]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-ro-footer]//*[@data-workspace-modal-trigger="document"]')->length)->toBeGreaterThan(0);
});
