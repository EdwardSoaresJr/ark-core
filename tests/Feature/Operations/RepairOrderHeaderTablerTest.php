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

test('the repair order header names the RO and carries the command bar below it', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForEstimateWorkspace();

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();
    $xpath = repairOrderHeaderXpath($html);
    $header = $xpath->query('//*['.repairOrderHeaderHasClass('page-header').']')->item(0);
    $bar = $xpath->query('.//*[@id="review-toolbar"]', $header)->item(0);

    expect($header)->toBeInstanceOf(DOMElement::class)
        ->and(trim($xpath->query('.//h2['.repairOrderHeaderHasClass('page-title').']', $header)->item(0)->textContent))->toBe('RO #'.$repairOrder->repair_order_id)
        ->and($xpath->query('.//*['.repairOrderHeaderHasClass('text-secondary').']['.repairOrderHeaderHasClass('mt-1').']', $header)->length)->toBe(0)
        ->and($bar)->toBeInstanceOf(DOMElement::class)
        ->and($bar->getAttribute('class'))->toBe('ark-ro-command-bar')
        ->and($xpath->query('ancestor::*['.repairOrderHeaderHasClass('col-12').'][1]', $bar)->length)->toBe(1)
        ->and($xpath->query('.//*['.repairOrderHeaderHasClass('ark-legacy').']', $header)->length)->toBe(0);
});

test('the command bar keeps the workflow selects, document actions, and print menu', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForEstimateWorkspace();
    $id = $repairOrder->repair_order_id;

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();
    $xpath = repairOrderHeaderXpath($html);
    $bar = $xpath->query('//*[@id="review-toolbar"]')->item(0);
    $actions = $xpath->query('.//*[@data-ro-command-actions]', $bar)->item(0);

    expect($xpath->query('.//*[@data-ro-command-workflow]//form[@id="repair-order-workflow"][@data-lifecycle-form][@action="'.route('operations.repair-orders.lifecycle.update', $repairOrder).'"]//select[@id="status-move-'.$id.'"][@data-lifecycle-select]', $bar)->length)->toBe(1)
        ->and($xpath->query('.//*['.repairOrderHeaderHasClass('ark-ro-command-bar__status').']//*[@data-lost-reason-panel][@hidden]', $bar)->length)->toBe(1)
        ->and($actions)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('.//button[@aria-label="Review Estimate Notes"]', $actions)->length)->toBe(1)
        ->and(preg_match('/aria-label="Review Estimate Notes"\s+@click="[^"]*task: \'review-estimate-notes\'/', $html))->toBe(1)
        ->and($xpath->query('.//button[@data-workspace-modal-trigger="document"][@aria-label="+ Add Document"]', $actions)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-ro-print-menu]//button[@data-bs-toggle="dropdown"][@aria-label="Print"]', $actions)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-ro-print-menu]//*[normalize-space()="Estimate PDF"]', $actions)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-ro-mode-toolbar-slot]', $bar)->length)->toBe(1);
});
