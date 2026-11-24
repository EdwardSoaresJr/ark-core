<?php

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Workboard\JobBoardLayout;
use App\Ark\Runtime\Preferences\JobBoardLayoutPreference;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('job board defaults to board layout with lane tabs and layout toggle', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Board Default');

    $html = $this->actingAs($advisor)->get(route('operations.index'))->assertOk()->getContent();
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    $board = $xpath->query('//*[@data-job-board]')->item(0);

    expect($board)->toBeInstanceOf(DOMElement::class)
        ->and($board->getAttribute('data-job-board-layout'))->toBe('board')
        ->and($xpath->query('.//*[@data-job-board-layout-toggle="board"][@aria-current="page"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-layout-toggle="list"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-lane-tabs]//*[@role="tab"]', $board)->length)->toBeGreaterThanOrEqual(6)
        ->and($xpath->query('.//*[@data-job-board-lane-tab="all"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-lanes]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-list]', $board)->length)->toBe(0)
        ->and($xpath->query('.//*[@id="ops-card-ro-'.$repairOrder->repair_order_id.'"]', $board)->length)->toBe(1);
});

test('job board list view renders one row per repair order with required columns', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $waiting = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'List Customer');
    $waiting->update(['concern_summary' => 'Brake noise diagnosis']);
    $parts = repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Parts Customer');
    $closed = repairOrderForCommunication(RepairOrderStatus::Closed, 'Closed Customer');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'list']))
        ->assertOk()
        ->assertCookie(JobBoardLayoutPreference::COOKIE_NAME, JobBoardLayout::List->value)
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $board = $xpath->query('//*[@data-job-board]')->item(0);

    expect($board)->toBeInstanceOf(DOMElement::class)
        ->and($board->getAttribute('data-job-board-layout'))->toBe('list')
        ->and($xpath->query('.//*[@data-job-board-list]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-lanes]', $board)->length)->toBe(0)
        ->and($xpath->query('.//*[@data-job-board-list-body]//tr[@data-job-board-row]', $board)->length)->toBe(2)
        ->and($xpath->query('.//*[@id="ops-list-ro-'.$waiting->repair_order_id.'"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-list-ro-'.$parts->repair_order_id.'"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-list-ro-'.$closed->repair_order_id.'"]', $board)->length)->toBe(0)
        ->and($xpath->query('.//*[@data-job-board-sort="ro"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-sort="customer"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-sort="vehicle"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-sort="status"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-sort="technician"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-sort="promised"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-sort="attention"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-sort="updated"]', $board)->length)->toBe(1)
        ->and($html)->toContain('Brake noise diagnosis')
        ->and($html)->toContain('List Customer')
        ->and($html)->toContain('data-job-board-list-mobile');

    $waitingRow = $xpath->query('.//*[@id="ops-list-ro-'.$waiting->repair_order_id.'"]', $board)->item(0);
    expect($waitingRow)->toBeInstanceOf(DOMElement::class)
        ->and($waitingRow->getAttribute('data-home-lane-key'))->not->toBe('')
        ->and($waitingRow->getAttribute('data-home-search'))->toContain('list customer')
        ->and($xpath->query('.//*[@data-job-board-row-ro]', $waitingRow)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-status]', $waitingRow)->length)->toBe(1);
});

test('job board list lane tab counts match board column totals', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Count A');
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Count B');
    repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Count Parts');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'list']))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    $approvalCount = $xpath->query('//*[@data-job-board-lane-count="waiting_approval"]')->item(0)?->textContent;
    $partsCount = $xpath->query('//*[@data-job-board-lane-count="parts"]')->item(0)?->textContent;
    $allCount = $xpath->query('//*[@data-job-board-lane-count="all"]')->item(0)?->textContent;

    expect($approvalCount)->toContain('2')
        ->and($partsCount)->toContain('1')
        ->and($allCount)->toContain('3');
});

test('job board list remembers layout from cookie when view query is absent', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::InProgress, 'Cookie List');

    $this->actingAs($advisor)
        ->withCookie(JobBoardLayoutPreference::COOKIE_NAME, JobBoardLayout::List->value)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('data-job-board-layout="list"', false)
        ->assertSee('data-job-board-list', false)
        ->assertDontSee('data-job-board-lanes', false);
});

test('job board list is denied to users without operations access', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('operations.index', ['view' => 'list']))
        ->assertForbidden();
});

test('job board list uncapped rows still preserve board card cap on board layout', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    foreach (range(1, 15) as $index) {
        repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Cap List '.$index);
    }

    $boardHtml = $this->actingAs($advisor)->get(route('operations.index'))->assertOk()->getContent();
    expect(substr_count($boardHtml, 'id="ops-card-ro-'))->toBe(12);

    $listHtml = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'list']))
        ->assertOk()
        ->getContent();

    expect(substr_count($listHtml, 'data-job-board-row'))->toBeGreaterThanOrEqual(30);
});
