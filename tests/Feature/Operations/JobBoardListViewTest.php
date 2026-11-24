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

test('job board is table-first by default without kanban or layout toggle', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Table Default');

    $html = $this->actingAs($advisor)->get(route('operations.index'))->assertOk()->getContent();
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $board = $xpath->query('//*[@data-job-board]')->item(0);

    expect($board)->toBeInstanceOf(DOMElement::class)
        ->and($board->getAttribute('data-job-board-layout'))->toBe('list')
        ->and($xpath->query('.//*[@data-job-board-layout-toggle]', $board)->length)->toBe(0)
        ->and($xpath->query('.//*[@data-job-board-lanes]', $board)->length)->toBe(0)
        ->and($xpath->query('.//*[@data-job-board-list]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-lane-tabs]//*[@data-job-board-lane-tab]', $board)->length)->toBeGreaterThanOrEqual(6)
        ->and($xpath->query('.//*[@data-job-board-lane-tab="all"][@aria-current="page"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-list-ro-'.$repairOrder->repair_order_id.'"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-card]', $board)->length)->toBeGreaterThanOrEqual(1)
        ->and($html)->not->toContain('ops-home-col-');
});

test('legacy view=board and view=list both serve the unified table job board', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Legacy View');

    foreach (['board', 'list'] as $view) {
        $html = $this->actingAs($advisor)
            ->get(route('operations.index', ['view' => $view]))
            ->assertOk()
            ->assertCookie(JobBoardLayoutPreference::COOKIE_NAME, JobBoardLayout::List->value)
            ->getContent();

        expect($html)->toContain('data-job-board-layout="list"')
            ->and($html)->toContain('data-job-board-list')
            ->and($html)->not->toContain('data-job-board-lanes')
            ->and($html)->not->toContain('data-job-board-layout-toggle');
    }
});

test('job board table renders dense columns including total and activity marks', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $waiting = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'List Customer');
    $waiting->update(['concern_summary' => 'Brake noise diagnosis']);
    $parts = repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Parts Customer');
    $closed = repairOrderForCommunication(RepairOrderStatus::Closed, 'Closed Customer');

    $html = $this->actingAs($advisor)->get(route('operations.index'))->assertOk()->getContent();
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $board = $xpath->query('//*[@data-job-board]')->item(0);

    expect($xpath->query('.//*[@data-job-board-list-body]//tr[@data-job-board-row]', $board)->length)->toBe(2)
        ->and($xpath->query('.//*[@id="ops-list-ro-'.$waiting->repair_order_id.'"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-list-ro-'.$parts->repair_order_id.'"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-list-ro-'.$closed->repair_order_id.'"]', $board)->length)->toBe(0)
        ->and($xpath->query('.//*[@data-job-board-sort="ro"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-total]', $board)->length)->toBe(2)
        ->and($xpath->query('.//*[@data-job-board-row-activity]', $board)->length)->toBe(2)
        ->and($html)->toContain('Brake noise diagnosis')
        ->and($html)->toContain('Attention')
        ->and($html)->toContain('data-job-board-list-mobile');
});

test('job board lane tab counts stay exact while page rows are bounded', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Count A');
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Count B');
    repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Count Parts');

    $html = $this->actingAs($advisor)->get(route('operations.index'))->assertOk()->getContent();
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $board = $xpath->query('//*[@data-job-board]')->item(0);

    expect(trim((string) $xpath->query('//*[@data-job-board-lane-count="waiting_approval"]')->item(0)?->textContent))->toBe('2')
        ->and(trim((string) $xpath->query('//*[@data-job-board-lane-count="parts"]')->item(0)?->textContent))->toBe('1')
        ->and(trim((string) $xpath->query('//*[@data-job-board-lane-count="all"]')->item(0)?->textContent))->toBe('3')
        ->and($board->getAttribute('data-job-board-list-filtered-total'))->toBe('3')
        ->and($board->getAttribute('data-job-board-list-queue-total'))->toBe('3');
});

test('job board search and lane filters agree with rendered rows', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $match = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Filter Match');
    $match->update(['concern_summary' => 'UNIQUE-FILTER-TOKEN']);
    repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Other Lane');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', [
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
        ->and(trim((string) $xpath->query('//*[@data-job-board-lane-count="all"]')->item(0)?->textContent))->toBe('2');
});

test('job board zero-result search shows empty state and keeps queue totals', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::InProgress, 'Still On Board');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['q' => 'NO-SUCH-RO-TOKEN']))
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

test('job board paginates and only projects the current page of rows', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    foreach (range(1, JobBoardListPage::PAGE_SIZE + 5) as $index) {
        repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Page List '.$index);
    }

    $pageOne = $this->actingAs($advisor)
        ->get(route('operations.index', ['lane' => 'waiting_approval']))
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

    $pageTwoHtml = $this->actingAs($advisor)
        ->get(route('operations.index', ['lane' => 'waiting_approval', 'page' => 2]))
        ->assertOk()
        ->getContent();

    $document2 = new DOMDocument;
    libxml_use_internal_errors(true);
    $document2->loadHTML($pageTwoHtml);
    libxml_clear_errors();
    $xpath2 = new DOMXPath($document2);

    expect($xpath2->query('//*[@data-job-board-list-body]//tr[@data-job-board-row]')->length)->toBe(5);
});

test('job board sort links set aria-sort on the active column', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Sort A');
    repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Sort B');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['sort' => 'customer', 'dir' => 'asc']))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-job-board-sort="customer"]/ancestor::th[@aria-sort="ascending"]')->length)->toBe(1);
});

test('job board is denied to users without operations access', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('operations.index'))
        ->assertForbidden();
});

test('job board high-volume request stays within page projection bound', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $volume = 120;

    foreach (range(1, $volume) as $index) {
        repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Volume '.$index);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $started = hrtime(true);

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['lane' => 'waiting_approval']))
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
