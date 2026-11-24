<?php

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Workboard\JobBoardLayout;
use App\Ark\Operations\Workboard\JobBoardListPage;
use App\Ark\Runtime\Preferences\JobBoardLayoutPreference;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Facades\DB;

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
        ->and($xpath->query('.//*[@data-job-board-lane-tabs]//*[@data-job-board-lane-tab]', $board)->length)->toBeGreaterThanOrEqual(6)
        ->and($xpath->query('.//*[@data-job-board-lane-tab="all"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-lanes]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-list]', $board)->length)->toBe(0)
        ->and($xpath->query('.//*[@role="tablist"]', $board)->length)->toBe(0)
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
        ->and($xpath->query('.//*[@data-job-board-sort="attention"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-sort="updated"]', $board)->length)->toBe(1)
        ->and($html)->toContain('Brake noise diagnosis')
        ->and($html)->toContain('List Customer')
        ->and($html)->toContain('Attention')
        ->and($html)->toContain('data-job-board-list-mobile')
        ->and($html)->toContain('Queue total');

    $waitingRow = $xpath->query('.//*[@id="ops-list-ro-'.$waiting->repair_order_id.'"]', $board)->item(0);
    expect($waitingRow)->toBeInstanceOf(DOMElement::class)
        ->and($waitingRow->getAttribute('data-home-lane-key'))->not->toBe('')
        ->and($waitingRow->getAttribute('data-home-search'))->toContain('list customer')
        ->and($xpath->query('.//*[@data-job-board-row-ro]', $waitingRow)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-status]', $waitingRow)->length)->toBe(1);
});

test('job board list lane tab counts stay exact while page rows are bounded', function () {
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

    $approvalCount = trim((string) $xpath->query('//*[@data-job-board-lane-count="waiting_approval"]')->item(0)?->textContent);
    $partsCount = trim((string) $xpath->query('//*[@data-job-board-lane-count="parts"]')->item(0)?->textContent);
    $allCount = trim((string) $xpath->query('//*[@data-job-board-lane-count="all"]')->item(0)?->textContent);
    $board = $xpath->query('//*[@data-job-board]')->item(0);

    expect($approvalCount)->toBe('2')
        ->and($partsCount)->toBe('1')
        ->and($allCount)->toBe('3')
        ->and($board->getAttribute('data-job-board-list-filtered-total'))->toBe('3')
        ->and($board->getAttribute('data-job-board-list-queue-total'))->toBe('3');
});

test('job board list search and lane filters agree with rendered rows', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $match = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Filter Match');
    $match->update(['concern_summary' => 'UNIQUE-FILTER-TOKEN']);
    repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Other Lane');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', [
            'view' => 'list',
            'lane' => 'waiting_approval',
            'q' => 'UNIQUE-FILTER-TOKEN',
        ]))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $board = $xpath->query('//*[@data-job-board]')->item(0);

    expect($xpath->query('.//*[@data-job-board-list-body]//tr[@data-job-board-row]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-list-ro-'.$match->repair_order_id.'"]', $board)->length)->toBe(1)
        ->and($board->getAttribute('data-job-board-list-filtered-total'))->toBe('1')
        ->and($xpath->query('.//*[@data-job-board-lane-tab="waiting_approval"][@aria-current="page"]', $board)->length)->toBe(1)
        ->and(trim((string) $xpath->query('//*[@data-job-board-lane-count="waiting_approval"]')->item(0)?->textContent))->toBe('1')
        ->and(trim((string) $xpath->query('//*[@data-job-board-lane-count="all"]')->item(0)?->textContent))->toBe('2');
});

test('job board list zero-result search shows empty state and keeps queue totals', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::InProgress, 'Still On Board');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'list', 'q' => 'NO-SUCH-RO-TOKEN']))
        ->assertOk()
        ->assertSee('No repair orders match these filters.', false)
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $board = $xpath->query('//*[@data-job-board]')->item(0);

    expect($board->getAttribute('data-job-board-list-filtered-total'))->toBe('0')
        ->and($board->getAttribute('data-job-board-list-queue-total'))->toBe('1')
        ->and($xpath->query('.//*[@data-job-board-list-body]//tr[@data-job-board-row]', $board)->length)->toBe(0);
});

test('job board list paginates and only projects the current page of rows', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    foreach (range(1, JobBoardListPage::PAGE_SIZE + 5) as $index) {
        repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Page List '.$index);
    }

    $pageOne = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'list', 'lane' => 'waiting_approval']))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($pageOne);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $board = $xpath->query('//*[@data-job-board]')->item(0);

    expect($xpath->query('.//*[@data-job-board-list-body]//tr[@data-job-board-row]', $board)->length)->toBe(JobBoardListPage::PAGE_SIZE)
        ->and($board->getAttribute('data-job-board-list-filtered-total'))->toBe((string) (JobBoardListPage::PAGE_SIZE + 5))
        ->and($xpath->query('.//*[@data-job-board-list-next]', $board)->length)->toBe(1);

    $pageTwo = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'list', 'lane' => 'waiting_approval', 'page' => 2]))
        ->assertOk()
        ->getContent();

    $document2 = new DOMDocument;
    libxml_use_internal_errors(true);
    $document2->loadHTML($pageTwo);
    libxml_clear_errors();
    $xpath2 = new DOMXPath($document2);

    expect($xpath2->query('//*[@data-job-board-list-body]//tr[@data-job-board-row]')->length)->toBe(5);
});

test('job board list sort links set aria-sort on the active column', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Sort A');
    repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Sort B');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'list', 'sort' => 'customer', 'dir' => 'asc']))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-job-board-sort="customer"]/ancestor::th[@aria-sort="ascending"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-sort="updated"]/ancestor::th[@aria-sort]')->length)->toBe(0);
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

test('job board list stays bounded while board keeps its visible card cap', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    foreach (range(1, 15) as $index) {
        repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Cap List '.$index);
    }

    $boardHtml = $this->actingAs($advisor)->get(route('operations.index'))->assertOk()->getContent();
    expect(substr_count($boardHtml, 'id="ops-card-ro-'))->toBe(12);

    $listHtml = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'list', 'lane' => 'waiting_approval']))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($listHtml);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-job-board-list-body]//tr[@data-job-board-row]')->length)->toBe(15)
        ->and(trim((string) $xpath->query('//*[@data-job-board-lane-count="waiting_approval"]')->item(0)?->textContent))->toBe('15');
});

test('job board list high-volume request stays within page projection bound', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $volume = 120;

    foreach (range(1, $volume) as $index) {
        repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Volume '.$index);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $started = hrtime(true);

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'list', 'lane' => 'waiting_approval']))
        ->assertOk()
        ->getContent();

    $elapsedMs = (hrtime(true) - $started) / 1_000_000;
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $board = $xpath->query('//*[@data-job-board]')->item(0);
    $rowCount = $xpath->query('.//*[@data-job-board-list-body]//tr[@data-job-board-row]', $board)->length;

    expect($rowCount)->toBe(JobBoardListPage::PAGE_SIZE)
        ->and($board->getAttribute('data-job-board-list-filtered-total'))->toBe((string) $volume)
        ->and($elapsedMs)->toBeLessThan(15000)
        ->and($queries)->toBeLessThan(250);

    $artifactDir = base_path('review-artifacts/issue-251-job-board-list');
    if (! is_dir($artifactDir)) {
        mkdir($artifactDir, 0777, true);
    }

    file_put_contents($artifactDir.'/PERF_LIST.json', json_encode([
        'volume' => $volume,
        'page_size' => JobBoardListPage::PAGE_SIZE,
        'rendered_rows' => $rowCount,
        'filtered_total' => (int) $board->getAttribute('data-job-board-list-filtered-total'),
        'elapsed_ms' => round($elapsedMs, 2),
        'query_count' => $queries,
        'measured_at' => now()->toIso8601String(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
});
