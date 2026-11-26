<?php

use App\Ark\Operations\Documents\PdfRuntimePaths;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Workboard\JobBoardLayout;
use App\Ark\Operations\Workboard\JobBoardListPage;
use App\Ark\Runtime\Preferences\JobBoardLayoutPreference;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Browsershot\Browsershot;
use Tests\Support\JobBoardAcceptanceScenario;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('job board defaults to cards with list and cards over the same result set', function () {
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
        ->and($board->getAttribute('data-job-board-layout'))->toBe('cards')
        ->and($board->getAttribute('data-job-board-layout-explicit'))->toBe('0')
        ->and($board->getAttribute('data-job-board-view-persist-url'))->toContain('/app/profile/job-board-view')
        ->and($xpath->query('.//*[@data-job-board-layout-toggle]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-layout-option="list"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-layout-option="cards"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-lanes]', $board)->length)->toBe(0)
        ->and($xpath->query('.//*[@data-job-board-list]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-cards]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-kanban]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-kanban-column]', $board)->length)->toBeGreaterThanOrEqual(5)
        ->and($xpath->query('.//*[@data-job-board-lane-tabs]//*[@data-job-board-lane-tab]', $board)->length)->toBeGreaterThanOrEqual(6)
        ->and($xpath->query('.//*[@data-job-board-lane-tab="all"][@aria-current="page"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-list-ro-'.$repairOrder->repair_order_id.'"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-card-ro-'.$repairOrder->repair_order_id.'"]', $board)->length)->toBe(1)
        ->and($html)->not->toContain('ops-home-col-')
        ->and($html)->toContain('ops-kanban-col-');
});

test('view=list and view=cards persist user preference cookie and keep shared board chrome', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Legacy View');

    $listHtml = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'list']))
        ->assertOk()
        ->assertPlainCookie(JobBoardLayoutPreference::COOKIE_NAME, JobBoardLayout::List->value)
        ->getContent();

    expect($advisor->fresh()->job_board_view)->toBe(JobBoardLayout::List->value)
        ->and($listHtml)->toContain('data-job-board-layout="list"')
        ->and($listHtml)->toContain('data-job-board-layout-toggle')
        ->and($listHtml)->toContain('data-job-board-list')
        ->and($listHtml)->toContain('data-job-board-cards')
        ->and($listHtml)->not->toContain('data-job-board-lanes');

    $cardsHtml = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'cards']))
        ->assertOk()
        ->assertPlainCookie(JobBoardLayoutPreference::COOKIE_NAME, JobBoardLayout::Cards->value)
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($cardsHtml);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $board = $xpath->query('//*[@data-job-board]')->item(0);

    expect($advisor->fresh()->job_board_view)->toBe(JobBoardLayout::Cards->value)
        ->and($board?->getAttribute('data-job-board-layout'))->toBe('cards')
        ->and($xpath->query('.//*[@data-job-board-layout-option="cards"][@aria-current="true"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-card-ro-'.$repairOrder->repair_order_id.'"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-list-ro-'.$repairOrder->repair_order_id.'"]', $board)->length)->toBe(1);
});

test('legacy view=board maps to cards preference', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Board Alias');

    $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'board']))
        ->assertOk()
        ->assertPlainCookie(JobBoardLayoutPreference::COOKIE_NAME, JobBoardLayout::Cards->value)
        ->assertSee('data-job-board-layout="cards"', false);

    expect($advisor->fresh()->job_board_view)->toBe(JobBoardLayout::Cards->value);
});

test('job board navigation stays free of view query and exposes preference scope', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Clean Url');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'list', 'lane' => 'waiting_approval', 'page' => 1]))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $board = $xpath->query('//*[@data-job-board]')->item(0);

    expect($board)->toBeInstanceOf(DOMElement::class)
        ->and($board->getAttribute('data-ark-ui-tenant'))->not->toBe('')
        ->and($board->getAttribute('data-ark-ui-user'))->toBe((string) $advisor->id)
        ->and($html)->toContain('name="ark-display-theme"')
        ->and($html)->toContain('name="ark-ui-tenant-key"')
        ->and($html)->not->toContain('data-job-board-view-input')
        ->and($html)->not->toContain('name="view"');

    foreach ($xpath->query('//*[@data-job-board-lane-tab]|//*[@data-job-board-sort]|//*[@data-job-board-list-next]|//*[@data-job-board-list-prev]') as $node) {
        if (! $node instanceof DOMElement) {
            continue;
        }
        $href = (string) $node->getAttribute('href');
        if ($href === '') {
            continue;
        }
        expect($href)->not->toContain('view=');
    }
});

test('job board cookie preference migrates into the user job_board_view column', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Cookie Pref');

    expect(Schema::hasColumn('users', JobBoardLayoutPreference::COLUMN))->toBeTrue()
        ->and($advisor->job_board_view)->toBeNull();

    $this->actingAs($advisor)
        ->withUnencryptedCookie(JobBoardLayoutPreference::COOKIE_NAME, JobBoardLayout::List->value)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('data-job-board-layout="list"', false)
        ->assertSee('data-job-board-layout-explicit="1"', false);

    expect($advisor->fresh()->job_board_view)->toBe(JobBoardLayout::List->value);
});

test('saved job board view survives a fresh request without cookies', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $advisor->forceFill(['job_board_view' => JobBoardLayout::List->value])->save();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Saved Pref');

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('data-job-board-layout="list"', false)
        ->assertSee('data-job-board-layout-explicit="1"', false);
});

test('job board view preference endpoint persists for the authenticated user only', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $other = User::factory()->create()->assignRole('advisor');

    $this->actingAs($advisor)
        ->patchJson(route('profile.job-board-view.update'), ['job_board_view' => 'list'])
        ->assertOk()
        ->assertJson(['job_board_view' => 'list']);

    expect($advisor->fresh()->job_board_view)->toBe('list')
        ->and($other->fresh()->job_board_view)->toBeNull();

    $this->actingAs($advisor)
        ->patchJson(route('profile.job-board-view.update'), ['job_board_view' => 'kanban'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['job_board_view']);

    expect($advisor->fresh()->job_board_view)->toBe('list');

    auth()->logout();

    $this->patchJson(route('profile.job-board-view.update'), ['job_board_view' => 'cards'])
        ->assertUnauthorized();
});

test('list and cards views render the same filtered repair order ids', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $match = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Shared Match');
    $match->update(['concern_summary' => 'SHARED-VIEW-TOKEN']);
    repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Other Lane');

    $params = ['q' => 'SHARED-VIEW-TOKEN', 'lane' => 'waiting_approval'];

    $listHtml = $this->actingAs($advisor)->get(route('operations.index', $params + ['view' => 'list']))->assertOk()->getContent();
    $cardsHtml = $this->actingAs($advisor)->get(route('operations.index', $params + ['view' => 'cards']))->assertOk()->getContent();

    foreach ([$listHtml, $cardsHtml] as $html) {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        $xpath = new DOMXPath($document);
        $board = $xpath->query('//*[@data-job-board]')->item(0);

        expect($board?->getAttribute('data-job-board-list-filtered-total'))->toBe('1')
            ->and($xpath->query('.//*[@id="ops-list-ro-'.$match->repair_order_id.'"]', $board)->length)->toBe(1)
            ->and($xpath->query('.//*[@id="ops-card-ro-'.$match->repair_order_id.'"]', $board)->length)->toBe(1)
            ->and($xpath->query('.//*[@data-job-board-lane-tab="waiting_approval"][@aria-current="page"]', $board)->length)->toBe(1);
    }
});

test('cards view preserves activity deep links and concern', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Card Links');
    $repairOrder->update(['concern_summary' => 'Card concern line']);

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'cards']))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $card = $xpath->query('//*[@id="ops-card-ro-'.$repairOrder->repair_order_id.'"]')->item(0);

    expect($card)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('.//*[@data-job-board-row-concern]', $card)->length)->toBe(1)
        ->and($html)->toContain('Card concern line');

    $estimate = $xpath->query('.//*[@data-job-card-mark="estimate"][@data-job-board-activity-link]', $card)->item(0);
    if ($estimate instanceof DOMElement) {
        expect((string) $estimate->getAttribute('href'))->toContain('#builder')
            ->and($estimate->getAttribute('data-bs-toggle'))->not->toBe('tooltip');
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

    $list = $xpath->query('.//*[@data-job-board-view="list"]', $board)->item(0);
    $cards = $xpath->query('.//*[@data-job-board-view="cards"]', $board)->item(0);

    expect($list)->toBeInstanceOf(DOMElement::class)
        ->and($cards)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('.//*[@data-job-board-list-body]//*[@data-job-board-row]', $list)->length)->toBe(2)
        ->and($xpath->query('.//*[@id="ops-list-ro-'.$waiting->repair_order_id.'"]', $list)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-list-ro-'.$parts->repair_order_id.'"]', $list)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-list-ro-'.$closed->repair_order_id.'"]', $list)->length)->toBe(0)
        ->and($xpath->query('.//*[@id="ops-card-ro-'.$waiting->repair_order_id.'"]', $cards)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-sort="ro"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-sort="customer"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-sort="vehicle"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-col="customer-vehicle"]', $list)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-customer-vehicle]', $list)->length)->toBe(2)
        ->and($xpath->query('.//*[@data-job-board-row-total]', $list)->length)->toBe(2)
        ->and($xpath->query('.//*[@data-job-board-row-activity]', $list)->length)->toBe(2)
        ->and($xpath->query('.//*[@data-job-board-row-promised]', $board)->length)->toBe(0)
        ->and($xpath->query('.//*[@data-job-board-row-parts]', $board)->length)->toBe(0)
        ->and($html)->toContain('Brake noise diagnosis')
        ->and($html)->toContain('Attention')
        ->and($html)->not->toContain('>Promised<')
        ->and($html)->not->toContain('>Parts</th>')
        ->and($html)->toContain('ark-job-board-hybrid')
        ->and($html)->not->toContain('data-job-board-list-mobile')
        ->and($html)->not->toContain('data-job-board-list-desktop');
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

    expect($xpath->query('.//*[@data-job-board-list-body]//*[@data-job-board-row]', $board)->length)->toBe(1)
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
        ->and($xpath->query('.//*[@data-job-board-list-body]//*[@data-job-board-row]', $board)->length)->toBe(0);
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

    expect($xpath->query('.//*[@data-job-board-list-body]//*[@data-job-board-row]', $board)->length)->toBe(JobBoardListPage::PAGE_SIZE)
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

    expect($xpath2->query('//*[@data-job-board-list-body]//*[@data-job-board-row]')->length)->toBe(5);
});

test('job board sort links set aria-sort on the active column', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Sort A');
    repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Sort B');

    $customerHtml = $this->actingAs($advisor)
        ->get(route('operations.index', ['sort' => 'customer', 'dir' => 'asc']))
        ->assertOk()
        ->getContent();

    $vehicleHtml = $this->actingAs($advisor)
        ->get(route('operations.index', ['sort' => 'vehicle', 'dir' => 'asc']))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($customerHtml);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    $vehicleDocument = new DOMDocument;
    libxml_use_internal_errors(true);
    $vehicleDocument->loadHTML($vehicleHtml);
    libxml_clear_errors();
    $vehicleXpath = new DOMXPath($vehicleDocument);

    expect($xpath->query('//*[@data-job-board-sort="customer"][@aria-sort="ascending"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-sort="customer"][@aria-current="true"]')->length)->toBe(1)
        ->and($vehicleXpath->query('//*[@data-job-board-sort="vehicle"][@aria-sort="ascending"]')->length)->toBe(1)
        ->and($vehicleXpath->query('//*[@data-job-board-sort="vehicle"][@aria-current="true"]')->length)->toBe(1);
});

test('job board customer vehicle header has a single class attribute', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Header Class');

    $html = $this->actingAs($advisor)->get(route('operations.index'))->assertOk()->getContent();

    expect(preg_match_all(
        '/<(?:div|th)\b[^>]*\bdata-job-board-col="customer-vehicle"[^>]*>/',
        $html,
        $matches
    ))->toBe(1);

    $header = $matches[0][0] ?? '';
    $classCount = preg_match_all('/\sclass="/', $header);

    expect($header)->toContain('ark-job-board-hybrid__sort')
        ->and($classCount)->toBe(1)
        ->and(substr_count($header, ' class='))->toBe(1);
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
    $rowCount = $xpath->query('.//*[@data-job-board-list-body]//*[@data-job-board-row]', $board)->length;

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

test('job board concern column prefers readable two-line clamp markup', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Concern Read');
    $repairOrder->update([
        'concern_summary' => 'Customer reports grinding brakes on every stop with a longer readable concern description for the board.',
    ]);
    $repairOrder->concerns()->first()?->update([
        'summary' => 'Customer reports grinding brakes on every stop with a longer readable concern description for the board.',
    ]);

    $html = $this->actingAs($advisor)->get(route('operations.index'))->assertOk()->getContent();

    expect($html)->toContain('ark-job-board-col-concern')
        ->and($html)->toContain('ark-job-board-concern-text')
        ->and($html)->toContain('data-job-board-row-concern')
        ->and($html)->toContain('grinding brakes on every stop');
});

test('job board watch attention is neutral while actionable attention stays prominent', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Watch Tone');

    $html = $this->actingAs($advisor)->get(route('operations.index'))->assertOk()->getContent();
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    $watch = $xpath->query('//*[@data-attention-tone="watch"]');
    $attention = $xpath->query('//*[@data-attention-tone="attention"]');
    $critical = $xpath->query('//*[@data-attention-tone="critical"]');

    if ($watch->length > 0) {
        $class = (string) $watch->item(0)?->attributes?->getNamedItem('class')?->nodeValue;
        expect($class)->toContain('text-secondary')
            ->and($class)->not->toContain('text-warning')
            ->and($class)->not->toContain('text-danger');
    }

    if ($attention->length > 0) {
        expect((string) $attention->item(0)?->attributes?->getNamedItem('class')?->nodeValue)->toContain('text-warning');
    }

    if ($critical->length > 0) {
        expect((string) $critical->item(0)?->attributes?->getNamedItem('class')?->nodeValue)->toContain('text-danger');
    }

    expect($watch->length + $attention->length + $critical->length)->toBeGreaterThan(0);
});

test('cards view paints a status-colored edge on every card and keeps attention as secondary copy', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Card Status Edge');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'cards']))
        ->assertOk()
        ->getContent();
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    $cards = $xpath->query('//*[@data-job-board-card]');
    expect($cards->length)->toBeGreaterThan(0);

    for ($i = 0; $i < $cards->length; $i++) {
        $card = $cards->item($i);
        expect($xpath->query('.//*[@data-status-edge-rail][contains(@class,"card-status-start")]', $card)->length)->toBe(1)
            ->and($card->attributes->getNamedItem('data-status-edge')?->nodeValue)->not->toBe('')
            ->and($xpath->query('.//*[contains(@class,"card-status-start") and contains(@class,"bg-red")]', $card)->length)->toBe(0);
    }

    $approvalEdges = $xpath->query('//*[@data-job-board-card][@data-status-edge="approval"]');
    expect($approvalEdges->length)->toBeGreaterThan(0);
    $approvalCard = $approvalEdges->item(0);
    expect($xpath->query('.//*[contains(@class,"card-status-start") and contains(@class,"bg-orange")]', $approvalCard)->length)->toBe(1);

    $watchCards = $xpath->query('//*[@data-job-board-card][@data-workboard-attention="watch"]');
    for ($i = 0; $i < $watchCards->length; $i++) {
        $card = $watchCards->item($i);
        $watchTone = $xpath->query('.//*[@data-attention-tone="watch"][contains(@class,"text-secondary")]', $card)->length;
        $concreteNext = $xpath->query('.//*[@data-next-kind="action"]', $card)->length;
        expect($watchTone + $concreteNext)->toBeGreaterThan(0);
    }
});

test('cards view uses hierarchical Tabler bands inside Kanban columns', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Card Hierarchy');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'cards']))
        ->assertOk()
        ->getContent();
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    $card = $xpath->query('//*[@id="ops-card-ro-'.$repairOrder->repair_order_id.'"]')->item(0);
    expect($card)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('//*[@data-job-board-kanban-track]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-kanban-column="waiting_approval"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-kanban-column="waiting_approval"]//*[@id="ops-card-ro-'.$repairOrder->repair_order_id.'"]')->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-ro]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-card-total]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-customer-vehicle]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-concern]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-status]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-technician]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-attention]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-card-activity]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-card-clock]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-vehicle]', $card)->length)->toBe(1)
        ->and($card->getAttribute('class'))->toContain('ark-job-board-card')
        ->and($card->getAttribute('class'))->not->toContain('h-100')
        ->and($card->getAttribute('class'))->not->toContain('card-sm');

    $footer = $xpath->query('.//*[contains(@class,"ark-job-board-card-band--footer")]', $card)->item(0);
    expect($footer)->toBeInstanceOf(DOMElement::class)
        ->and($footer->getAttribute('class'))->not->toContain('mt-auto');

    $css = (string) file_get_contents(resource_path('css/tabler.css'));
    expect($css)
        ->toContain('.ark-job-board-kanban-column .ark-job-board-card')
        ->toContain('height: auto')
        ->toContain('container-type: inline-size')
        ->toContain('grid-template-columns: repeat(5, minmax(0, 1fr))')
        ->toContain('@container ark-job-board-kanban (min-width: 1200px)')
        ->toContain('--ark-kanban-sticky-top')
        ->toContain('max-height: none')
        ->not->toContain('max-height: min(70vh, 44rem)')
        ->not->toContain('--ark-kanban-column-width: 20rem')
        ->not->toContain('@container ark-job-board-kanban (min-width: 980px)')
        ->toMatch('/\.ark-job-board-cards\s+\.ark-job-board-card-band--concern\s*\{[^}]*flex:\s*0\s+0\s+auto/s')
        ->not->toMatch('/\.ark-job-board-cards\s+\.ark-job-board-card-band--concern\s*\{[^}]*flex:\s*1\s+1\s+auto/s');
});

test('cards kanban marks the first lane active for mobile single-column Cards', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::Estimate, 'Mobile Single Lane');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'cards']))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    $kanban = $xpath->query('//*[@data-job-board-kanban]')->item(0);
    expect($kanban)->toBeInstanceOf(DOMElement::class)
        ->and($kanban->getAttribute('data-job-board-kanban-active-column'))->not->toBe('')
        ->and($xpath->query('//*[@data-job-board-kanban-column-select]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-kanban-column-select]/@aria-label')->item(0)?->nodeValue)
        ->toBe('Kanban column');

    $active = $xpath->query('//*[@data-job-board-kanban-column][contains(concat(" ", normalize-space(@class), " "), " is-kanban-column-active ")]');
    expect($active->length)->toBe(1)
        ->and($active->item(0)?->getAttribute('data-job-board-kanban-column'))
        ->toBe($kanban->getAttribute('data-job-board-kanban-active-column'));

    $css = (string) file_get_contents(resource_path('css/tabler.css'));
    expect($css)
        ->toContain('.ark-job-board-kanban-column.is-kanban-column-active')
        ->toContain('Re-assert mobile single-lane after container')
        ->toMatch('/\.ark-job-board-kanban-column\s*\{[^}]*display:\s*none;[^}]*width:\s*100%/s');
});

test('cards kanban shows every lane column and ignores the active list lane tab', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $waiting = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Kanban Waiting');
    $parts = repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Kanban Parts');
    $progress = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Kanban Progress');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', [
            'view' => 'cards',
            'lane' => 'waiting_approval',
        ]))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-job-board-kanban-column]')->length)->toBeGreaterThanOrEqual(5)
        ->and($xpath->query('//*[@data-job-board-list-chrome]')->length)->toBeGreaterThan(0)
        ->and($xpath->query('//*[@id="ops-card-ro-'.$waiting->repair_order_id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="ops-card-ro-'.$parts->repair_order_id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="ops-card-ro-'.$progress->repair_order_id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-kanban-column="waiting_approval"]//*[@id="ops-card-ro-'.$waiting->repair_order_id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-kanban-column="parts"]//*[@id="ops-card-ro-'.$parts->repair_order_id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-kanban-column="work_in_progress"]//*[@id="ops-card-ro-'.$progress->repair_order_id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-kanban-count="waiting_approval"]')->item(0)?->textContent)->toContain('1');

    $listHtml = $this->actingAs($advisor)
        ->get(route('operations.index', [
            'view' => 'list',
            'lane' => 'waiting_approval',
        ]))
        ->assertOk()
        ->getContent();

    expect($listHtml)->toContain('id="ops-list-ro-'.$waiting->repair_order_id.'"')
        ->and($listHtml)->not->toContain('id="ops-list-ro-'.$parts->repair_order_id.'"');
});

test('cards kanban search keeps matches grouped in their workflow columns', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $match = repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'UniqueKanbanNeedle');
    repairOrderForCommunication(RepairOrderStatus::InProgress, 'Other Vehicle');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', [
            'view' => 'cards',
            'q' => 'UniqueKanbanNeedle',
        ]))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@id="ops-card-ro-'.$match->repair_order_id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-kanban-column="parts"]//*[@id="ops-card-ro-'.$match->repair_order_id.'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-kanban-count="parts"]')->item(0)?->textContent)->toContain('1')
        ->and((int) trim((string) $xpath->query('//*[@data-job-board-kanban-count="work_in_progress"]')->item(0)?->textContent))->toBe(0);
});

test('job board click contract keeps RO vehicle and customer destinations separate', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Link Contract');
    $roId = $repairOrder->repair_order_id;
    $customerId = $repairOrder->customer_id;

    $html = $this->actingAs($advisor)->get(route('operations.index'))->assertOk()->getContent();
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $row = $xpath->query('//*[@id="ops-list-ro-'.$roId.'"]')->item(0);

    expect($row)->toBeInstanceOf(DOMElement::class)
        ->and($row->attributes->getNamedItem('data-status-edge')?->nodeValue)->not->toBeNull()
        ->and(str_contains((string) $row->getAttribute('class'), 'ark-status-edge--'))->toBeTrue()
        ->and(str_contains((string) $row->getAttribute('class'), 'ark-job-board-hybrid-record--attention'))->toBeFalse();

    $roHref = (string) $xpath->query('.//*[@data-job-board-row-ro]', $row)->item(0)?->attributes?->getNamedItem('href')?->nodeValue;
    $vehicleHref = (string) $xpath->query('.//*[@data-job-board-row-vehicle]', $row)->item(0)?->attributes?->getNamedItem('href')?->nodeValue;
    $customerHref = (string) $xpath->query('.//*[@data-job-board-row-customer]', $row)->item(0)?->attributes?->getNamedItem('href')?->nodeValue;

    expect($roHref)->toContain('/repair-orders/'.$roId)
        ->and($vehicleHref)->toContain('/repair-orders/'.$roId)
        ->and($customerHref)->toContain('/customers/'.$customerId)
        ->and($customerHref)->not->toContain('/repair-orders/')
        ->and($xpath->query('.//*[@data-job-board-row-customer-vehicle]', $row)->length)->toBe(1)
        ->and($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ark-job-board-hybrid-record__topline ")]', $row)->length)->toBe(1)
        ->and($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ark-job-board-hybrid-record__vehicle ")]', $row)->length)->toBe(1)
        ->and($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ark-job-board-hybrid-record__customer ")]', $row)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-concern]//a', $row)->length)->toBe(0)
        ->and($xpath->query('.//*[@data-job-board-row-promised]', $row)->length)->toBe(0)
        ->and($xpath->query('.//*[@data-job-board-row-parts]', $row)->length)->toBe(0);

    $toplineHtml = '';
    $toplineNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ark-job-board-hybrid-record__topline ")]', $row)->item(0);
    if ($toplineNode instanceof DOMElement) {
        $toplineHtml = $document->saveHTML($toplineNode) ?: '';
    }
    $vehiclePos = strpos($toplineHtml, 'ark-job-board-hybrid-record__vehicle');
    $roPos = strpos($toplineHtml, 'ark-job-board-hybrid-record__ro');
    $customerPos = strpos($toplineHtml, 'ark-job-board-hybrid-record__customer');
    expect($vehiclePos)->not->toBeFalse()
        ->and($roPos)->not->toBeFalse()
        ->and($customerPos)->not->toBeFalse()
        ->and($vehiclePos)->toBeLessThan((int) $roPos)
        ->and($roPos)->toBeLessThan((int) $customerPos);
});

test('job board concept c polish keeps denser hybrid chrome and compact mobile sort', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Polish Density');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', [
            'view' => 'list',
            'lane' => 'waiting_approval',
            'sort' => 'total',
            'dir' => 'asc',
        ]))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $row = $xpath->query('//*[@data-job-board-list-body]//*[@data-job-board-row]')->item(0);

    expect($row)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ark-job-board-hybrid-record__middle ")]', $row)->length)->toBe(1)
        ->and($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ark-job-board-hybrid-record__finance-stack ")]', $row)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-status]', $row)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-technician]', $row)->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-list-sort-compact]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-list-sort-compact]//select[@name="sort"]/option[@value="total"][@selected]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-lane-tabs]//*[contains(concat(" ", normalize-space(@class), " "), " ark-job-board-lane-tabs__track ")]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-lane-tab="waiting_approval"][contains(concat(" ", normalize-space(@class), " "), " ark-job-board-lane-tabs__tab ")][@aria-current="page"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-lane-queue]//select[@name="lane"]/option[@value="waiting_approval"][@selected]')->length)->toBe(1)
        ->and($html)->toContain('ark-job-board-lane-tabs__count')
        ->and($html)->toContain('ark-job-board-hybrid__sort--compact')
        ->and($html)->toContain('ark-job-board-hybrid__sort--links')
        ->and($html)->not->toContain('data-job-board-list-mobile')
        ->and($html)->not->toContain('ark-job-board-table--grid');

    $css = (string) file_get_contents(resource_path('css/tabler.css'));
    expect($css)
        ->toContain('.ark-job-board-lane-tabs__track')
        ->toContain('.ark-job-board-lane-tabs__queue')
        ->toContain('overflow-x: auto')
        ->toContain('.ark-job-board-hybrid__sort--compact')
        ->toMatch("/grid-template-areas:\\s*'identity money'\\s*'middle middle'\\s*'workflow open'/s")
        ->toContain('display: contents');
});

test('job board mobile queue select mirrors lane tabs with counts and preserves query params', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Queue Select A');
    repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Queue Select B');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', [
            'view' => 'list',
            'lane' => 'waiting_approval',
            'q' => 'Queue Select',
            'sort' => 'total',
            'dir' => 'asc',
        ]))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $queue = $xpath->query('//*[@data-job-board-lane-queue]')->item(0);

    expect($queue)->toBeInstanceOf(DOMElement::class);

    $queueHtml = $document->saveHTML($queue) ?: '';
    $optionLabels = [];
    foreach ($xpath->query('.//select[@name="lane"]/option', $queue) as $option) {
        $optionLabels[(string) $option->attributes?->getNamedItem('value')?->nodeValue] = trim((string) $option->textContent);
    }

    expect($xpath->query('.//select[@name="lane"]/option', $queue)->length)->toBeGreaterThanOrEqual(6)
        ->and($xpath->query('.//select[@name="lane"]/option[@value="waiting_approval"][@selected]', $queue)->length)->toBe(1)
        ->and($xpath->query('.//input[@type="hidden"][@name="q"][@value="Queue Select"]', $queue)->length)->toBe(1)
        ->and($xpath->query('.//input[@type="hidden"][@name="sort"][@value="total"]', $queue)->length)->toBe(1)
        ->and($xpath->query('.//input[@type="hidden"][@name="dir"][@value="asc"]', $queue)->length)->toBe(1)
        ->and($xpath->query('.//input[@type="hidden"][@name="page"]', $queue)->length)->toBe(0)
        ->and($optionLabels['waiting_approval'] ?? '')->toContain('Waiting Approval (')
        ->and($optionLabels['parts'] ?? '')->toContain('Waiting Parts (')
        ->and($optionLabels['all'] ?? '')->toMatch('/^All \(\d+\)$/')
        ->and((int) trim((string) $xpath->query('//*[@data-job-board-lane-count="waiting_approval"]')->item(0)?->textContent))
        ->toBe((int) preg_replace('/.*\((\d+)\)$/', '$1', $optionLabels['waiting_approval'] ?? '0'))
        ->and($queueHtml)->not->toContain('name="view"');

    expect($xpath->query('//*[@data-job-board-list-chrome]//*[@data-job-board-lane-queue]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-list-chrome]//*[@data-job-board-lane-tabs]')->length)->toBe(1);

    $cardsHtml = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'cards', 'lane' => 'parts']))
        ->assertOk()
        ->getContent();

    $cardsDocument = new DOMDocument;
    libxml_use_internal_errors(true);
    $cardsDocument->loadHTML($cardsHtml);
    libxml_clear_errors();
    $cardsXpath = new DOMXPath($cardsDocument);
    $board = $cardsXpath->query('//*[@data-job-board]')->item(0);

    expect($board)->toBeInstanceOf(DOMElement::class)
        ->and($board->getAttribute('data-job-board-layout'))->toBe('cards')
        ->and($cardsXpath->query('.//*[@data-job-board-list-chrome]//*[@data-job-board-lane-queue]//select[@name="lane"]/option[@value="parts"][@selected]', $board)->length)->toBe(1)
        ->and($cardsXpath->query('.//*[@data-job-board-lane-tab="parts"][@aria-current="page"]', $board)->length)->toBe(1)
        ->and($cardsHtml)->toContain('applies only in List view');

    $listAgain = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'list', 'lane' => 'parts']))
        ->assertOk()
        ->getContent();

    expect($listAgain)->toContain('data-job-board-layout="list"')
        ->and($listAgain)->toContain('data-job-board-lane-tab="parts"')
        ->and($listAgain)->toContain('aria-current="page"');

    $css = (string) file_get_contents(resource_path('css/tabler.css'));
    expect($css)
        ->toContain('@media (max-width: 991.98px)')
        ->toContain('.ark-job-board-lane-tabs__track')
        ->toContain('.ark-job-board-lane-tabs__queue')
        ->toContain('[data-job-board-list-chrome]')
        ->toContain('display: none')
        ->toContain('display: flex');
});

test('job board acceptance scenario surfaces tech status totals without promised or parts columns', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    JobBoardAcceptanceScenario::seed($advisor);

    $html = $this->actingAs($advisor)->get(route('operations.index'))->assertOk()->getContent();
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    expect($html)->toContain('SYN Brakes Demo')
        ->and($html)->toContain('Alex Rivera')
        ->and($html)->toContain('Jordan Lee')
        ->and($html)->toContain('grinding front brakes')
        ->and($html)->toContain('Waiting Parts')
        ->and($html)->toContain('ark-job-board-hybrid')
        ->and($html)->not->toContain('ark-job-board-table--grid')
        ->and($html)->not->toContain('data-job-board-list-mobile')
        ->and($xpath->query('//*[@data-job-board-col="customer-vehicle"]', $document)->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-sort="customer"]', $document)->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-sort="vehicle"]', $document)->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-row-customer-vehicle]', $document)->length)->toBeGreaterThan(0)
        ->and($xpath->query('//*[@data-job-board-row-promised]', $document)->length)->toBe(0)
        ->and($xpath->query('//*[@data-job-board-row-parts]', $document)->length)->toBe(0)
        ->and($html)->not->toContain('>Promised<')
        ->and($html)->not->toContain('>Parts</th>')
        ->and($html)->toContain('$425')
        ->and($html)->toContain('$1,066');
});

test('job board concern column falls back to concern row summaries when RO summary is empty', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Empty Summary');
    $repairOrder->update(['concern_summary' => '']);
    $repairOrder->concerns()->first()?->update([
        'summary' => 'Imported transmission concern from concern rows',
        'position' => 1,
    ]);
    $repairOrder->concerns()->create([
        'summary' => 'Secondary fluid service job',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 2,
    ]);

    $html = $this->actingAs($advisor)->get(route('operations.index', ['view' => 'list']))->assertOk()->getContent();

    expect($html)->toContain('Imported transmission concern from concern rows')
        ->and($html)->toContain('Secondary fluid service job')
        ->and($html)->toContain('ark-job-board-concern-stack');
});

test('job board exposes tech total and attention sort controls with aria-sort', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Sort Extra A');
    repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Sort Extra B');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['sort' => 'tech', 'dir' => 'asc']))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-job-board-sort="tech"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-sort="total"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-sort="attention"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-sort="tech"][@aria-sort="ascending"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-sort="tech"][@aria-current="true"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-lane-tabs]//a[contains(@class,"active")][@aria-current="page"]')->length)->toBe(1);

    $totalHtml = $this->actingAs($advisor)
        ->get(route('operations.index', ['sort' => 'total', 'dir' => 'desc']))
        ->assertOk()
        ->getContent();

    expect($totalHtml)->toContain('data-job-board-sort="total"')
        ->and($totalHtml)->toContain('aria-sort="descending"');
});

test('job board total sort uses numeric cents not formatted strings', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $low = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Low Total');
    $high = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'High Total');
    $low->lines()->update(['unit_price_cents' => 500, 'subtotal_cents' => 500, 'total_cents' => 500]);
    $high->lines()->update(['unit_price_cents' => 25000, 'subtotal_cents' => 25000, 'total_cents' => 25000]);

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['sort' => 'total', 'dir' => 'desc', 'lane' => 'waiting_approval']))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $rows = $xpath->query('//*[@data-job-board-list-body]//*[@data-job-board-row]');

    expect($rows->length)->toBeGreaterThanOrEqual(2);
    $firstId = (string) $rows->item(0)?->attributes?->getNamedItem('data-ro-id')?->nodeValue;
    $secondId = (string) $rows->item(1)?->attributes?->getNamedItem('data-ro-id')?->nodeValue;

    expect($firstId)->toBe((string) $high->repair_order_id)
        ->and($secondId)->toBe((string) $low->repair_order_id);
});

test('kanban cards stay content-height instead of filling the column', function () {
    $chrome = PdfRuntimePaths::resolveChromePath();
    $node = PdfRuntimePaths::resolveNodeBinary();
    $nodeModules = collect([
        base_path('node_modules'),
        getenv('PUPPETEER_NODE_MODULES') ?: null,
    ])->first(fn ($path) => is_string($path) && is_dir($path.DIRECTORY_SEPARATOR.'puppeteer'));

    if (
        ! is_string($chrome) || $chrome === '' || ! is_executable($chrome)
        || ! is_string($node) || $node === '' || ! is_executable($node)
        || ! is_string($nodeModules)
    ) {
        $this->markTestSkipped('Chromium, Node, and puppeteer are required for Kanban card height measurements.');
    }

    $sourceCss = (string) file_get_contents(resource_path('css/tabler.css'));
    expect($sourceCss)
        ->toMatch('/\.ark-job-board-kanban-column\s+\.ark-job-board-card\s*\{[^}]*flex:\s*0\s+0\s+auto[^}]*height:\s*auto/s')
        ->toMatch('/\.ark-job-board-cards\s+\.ark-job-board-card-band--concern\s*\{[^}]*flex:\s*0\s+0\s+auto/s')
        ->toContain('grid-template-columns: repeat(5, minmax(0, 1fr))')
        ->not->toContain('max-height: min(70vh, 44rem)');

    // Mirror compact card + fluid column sizing for a hermetic layout measurement.
    $html = <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
.ark-job-board-kanban { --ark-kanban-gap: 0.35rem; --ark-kanban-card-gap: 0.5rem; container-type: inline-size; }
.ark-job-board-kanban-scroller { overflow-x: visible; overflow-y: visible; }
.ark-job-board-kanban-track {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    align-items: start;
    gap: var(--ark-kanban-gap);
    width: 100%;
}
.ark-job-board-kanban-column {
    display: flex;
    flex-direction: column;
    min-width: 0;
    max-height: none;
}
.ark-job-board-kanban-column-body {
    flex: 0 0 auto;
    overflow: visible;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    justify-content: flex-start;
    gap: var(--ark-kanban-card-gap);
    padding: 0.45rem;
}
.ark-job-board-kanban-column .ark-job-board-card {
    flex: 0 0 auto;
    height: auto;
    align-self: stretch;
}
.ark-job-board-cards .ark-job-board-card-band--concern {
    flex: 0 0 auto;
    min-height: 0;
}
.card { display: flex; flex-direction: column; background: #fff; border: 1px solid #ddd; }
.card-body { flex: 0 0 auto; }
.card-header { padding: 0.45rem; }
.ark-job-board-card-body { display: flex; flex-direction: column; gap: 0.4rem; padding: 0.55rem 0.6rem; min-height: 0; }
.ark-job-board-card-band--identity { display: flex; align-items: baseline; gap: 0.5rem; }
.ark-job-board-card-band--footer { border-top: 1px solid #ddd; margin-top: 0.15rem; padding-top: 0.45rem; }
.fw-bold { font-weight: 700; }
.ms-auto { margin-left: auto; }
.small { font-size: 0.8125rem; }
.text-secondary { color: #6c757d; }
</style>
</head>
<body style="margin:0;font-family:system-ui,sans-serif">
<div class="ark-job-board-cards ark-job-board-kanban" style="width:1200px">
  <div class="ark-job-board-kanban-scroller">
    <div class="ark-job-board-kanban-track">
      <section class="ark-job-board-kanban-column card">
        <header class="ark-job-board-kanban-column-head card-header"><h2 class="card-title mb-0">Estimates <span>3</span></h2></header>
        <div class="ark-job-board-kanban-column-body card-body" data-job-board-kanban-column-body>
          <article class="card ark-job-board-card" data-job-board-card data-measure-card="short">
            <div class="card-body ark-job-board-card-body">
              <div class="ark-job-board-card-band ark-job-board-card-band--identity"><span class="fw-bold">RO #1001</span><span class="ms-auto fw-bold">$120</span></div>
              <div class="ark-job-board-card-band ark-job-board-card-band--party"><div>2018 Honda Civic</div><div class="small text-secondary">Pat Customer</div></div>
              <div class="ark-job-board-card-band ark-job-board-card-band--concern" data-measure-concern>Oil change</div>
              <div class="ark-job-board-card-band ark-job-board-card-band--state" data-measure-state>Building Estimate · Unassigned</div>
              <div class="ark-job-board-card-band ark-job-board-card-band--next" data-measure-next>Next Review estimate</div>
              <div class="ark-job-board-card-band ark-job-board-card-band--footer" data-measure-footer>Activity · 2h ago</div>
            </div>
          </article>
          <article class="card ark-job-board-card" data-job-board-card data-measure-card="tall">
            <div class="card-body ark-job-board-card-body">
              <div class="ark-job-board-card-band ark-job-board-card-band--identity"><span class="fw-bold">RO #1002</span><span class="ms-auto fw-bold">$2,400</span></div>
              <div class="ark-job-board-card-band ark-job-board-card-band--party"><div>2020 Mercedes-Benz GLA-Class Gla250-4m</div><div class="small text-secondary">Long Name Customer</div></div>
              <div class="ark-job-board-card-band ark-job-board-card-band--concern" data-measure-concern>Customer reports intermittent grinding from the front brakes when stopping from highway speeds after rain</div>
              <div class="ark-job-board-card-band ark-job-board-card-band--state" data-measure-state>Needs Diagnosis · Edward Soares</div>
              <div class="ark-job-board-card-band ark-job-board-card-band--next" data-measure-next>Next Start diagnosis</div>
              <div class="ark-job-board-card-band ark-job-board-card-band--footer" data-measure-footer>Activity · 28m ago</div>
            </div>
          </article>
          <article class="card ark-job-board-card" data-job-board-card data-measure-card="mid">
            <div class="card-body ark-job-board-card-body">
              <div class="ark-job-board-card-band ark-job-board-card-band--identity"><span class="fw-bold">RO #1003</span><span class="ms-auto fw-bold">$890</span></div>
              <div class="ark-job-board-card-band ark-job-board-card-band--party"><div>2016 Ram 2500</div><div class="small text-secondary">Shop Truck</div></div>
              <div class="ark-job-board-card-band ark-job-board-card-band--concern" data-measure-concern>Transmission service and fluid flush</div>
              <div class="ark-job-board-card-band ark-job-board-card-band--state" data-measure-state>Building Estimate · Unassigned</div>
              <div class="ark-job-board-card-band ark-job-board-card-band--next" data-measure-next>Next Build estimate</div>
              <div class="ark-job-board-card-band ark-job-board-card-band--footer" data-measure-footer>Activity · 1d ago</div>
            </div>
          </article>
        </div>
      </section>
      <section class="ark-job-board-kanban-column card" data-extra-col="1"><header class="card-header">Waiting Approval</header><div class="ark-job-board-kanban-column-body card-body"></div></section>
      <section class="ark-job-board-kanban-column card" data-extra-col="2"><header class="card-header">Waiting Parts</header><div class="ark-job-board-kanban-column-body card-body"></div></section>
      <section class="ark-job-board-kanban-column card" data-extra-col="3"><header class="card-header">Work in Progress</header><div class="ark-job-board-kanban-column-body card-body"></div></section>
      <section class="ark-job-board-kanban-column card" data-extra-col="4"><header class="card-header">Completed</header><div class="ark-job-board-kanban-column-body card-body"></div></section>
    </div>
  </div>
</div>
</body>
</html>
HTML;

    $browser = Browsershot::html($html)
        ->windowSize(1366, 900)
        ->setChromePath($chrome)
        ->setNodeBinary($node)
        ->setNodeModulePath($nodeModules);

    if ($npm = PdfRuntimePaths::resolveNpmBinary($node)) {
        $browser->setNpmBinary($npm);
    }

    if (config('services.pdf.no_sandbox')) {
        $browser->noSandbox();
    }

    $payload = json_decode($browser->evaluate(<<<'JS'
(() => {
  const board = document.querySelector('.ark-job-board-kanban');
  const track = document.querySelector('.ark-job-board-kanban-track');
  const body = document.querySelector('[data-job-board-kanban-column-body]');
  const cards = [...document.querySelectorAll('[data-job-board-card]')];
  const columns = [...document.querySelectorAll('.ark-job-board-kanban-column')];
  const measure = (card) => {
    const concern = card.querySelector('[data-measure-concern]');
    const state = card.querySelector('[data-measure-state]');
    const footer = card.querySelector('[data-measure-footer]');
    const concernBottom = concern.getBoundingClientRect().bottom;
    const stateTop = state.getBoundingClientRect().top;
    const footerTop = footer.getBoundingClientRect().top;
    return {
      height: card.getBoundingClientRect().height,
      gapConcernToState: stateTop - concernBottom,
      gapConcernToFooter: footerTop - concernBottom,
      heightCss: getComputedStyle(card).height,
      flexGrow: getComputedStyle(card).flexGrow,
      concernFlex: getComputedStyle(concern).flexGrow,
    };
  };
  const heights = cards.map((card) => card.getBoundingClientRect().height);
  const colWidths = columns.map((col) => Math.round(col.getBoundingClientRect().width));
  const trackStyle = getComputedStyle(track);
  const bodyStyle = getComputedStyle(body);
  return JSON.stringify({
    bodyHeight: body.getBoundingClientRect().height,
    bodyOverflowY: bodyStyle.overflowY,
    cards: cards.map(measure),
    maxCardHeight: Math.max(...heights),
    sumCardHeights: heights.reduce((a, b) => a + b, 0),
    cardCount: cards.length,
    viewport: window.innerWidth,
    boardWidth: Math.round(board.getBoundingClientRect().width),
    trackWidth: Math.round(track.getBoundingClientRect().width),
    trackDisplay: trackStyle.display,
    trackColumns: trackStyle.gridTemplateColumns,
    columnCount: columns.length,
    columnWidths: colWidths,
    documentScrollWidth: document.documentElement.scrollWidth,
    documentClientWidth: document.documentElement.clientWidth,
  });
})()
JS), true);

    expect($payload)->toBeArray()
        ->and($payload['viewport'])->toBe(1366)
        ->and($payload['cardCount'])->toBe(3)
        ->and($payload['columnCount'])->toBe(5)
        ->and($payload['trackDisplay'])->toBe('grid')
        ->and($payload['bodyOverflowY'])->toBe('visible')
        ->and($payload['trackWidth'])->toBeLessThanOrEqual($payload['boardWidth'] + 1)
        ->and($payload['documentScrollWidth'])->toBeLessThanOrEqual($payload['documentClientWidth'] + 1)
        ->and($payload['maxCardHeight'])->toBeLessThan(320.0);

    // Five fluid columns should share ~1200px board width (~240px each, ±20).
    foreach ($payload['columnWidths'] as $width) {
        expect($width)->toBeGreaterThan(200)
            ->and($width)->toBeLessThan(280);
    }

    // Body grows with cards (no nested viewport); card heights stay content-driven.
    expect($payload['sumCardHeights'])->toBeLessThanOrEqual($payload['bodyHeight'] + 1)
        ->and($payload['bodyHeight'])->toBeLessThan($payload['sumCardHeights'] + 40);

    foreach ($payload['cards'] as $card) {
        expect($card['flexGrow'])->toBe('0')
            ->and($card['concernFlex'])->toBe('0')
            ->and($card['gapConcernToState'])->toBeLessThan(24.0)
            ->and($card['gapConcernToFooter'])->toBeLessThan(120.0)
            ->and($card['height'])->toBeLessThan(320.0);
    }
});
