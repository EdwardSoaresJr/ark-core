<?php

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Workboard\JobBoardLayout;
use App\Ark\Operations\Workboard\JobBoardListPage;
use App\Ark\Runtime\Preferences\JobBoardLayoutPreference;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\JobBoardAcceptanceScenario;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('job board defaults to auto layout with list and cards over the same result set', function () {
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
        ->and($board->getAttribute('data-job-board-layout'))->toBe('auto')
        ->and($board->getAttribute('data-job-board-layout-explicit'))->toBe('0')
        ->and($xpath->query('.//*[@data-job-board-layout-toggle]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-layout-option="list"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-layout-option="cards"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-lanes]', $board)->length)->toBe(0)
        ->and($xpath->query('.//*[@data-job-board-list]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-cards]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-lane-tabs]//*[@data-job-board-lane-tab]', $board)->length)->toBeGreaterThanOrEqual(6)
        ->and($xpath->query('.//*[@data-job-board-lane-tab="all"][@aria-current="page"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-list-ro-'.$repairOrder->repair_order_id.'"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-card-ro-'.$repairOrder->repair_order_id.'"]', $board)->length)->toBe(1)
        ->and($html)->not->toContain('ops-home-col-');
});

test('view=list and view=cards persist cookie and keep shared board chrome', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Legacy View');

    $listHtml = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'list']))
        ->assertOk()
        ->assertPlainCookie(JobBoardLayoutPreference::COOKIE_NAME, JobBoardLayout::List->value)
        ->getContent();

    expect($listHtml)->toContain('data-job-board-layout="list"')
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

    expect($board?->getAttribute('data-job-board-layout'))->toBe('cards')
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
});

test('job board cookie preference selects cards without a user settings column', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Cookie Pref');

    expect(\Illuminate\Support\Facades\Schema::hasColumn('users', 'job_board_layout'))->toBeFalse();

    $this->actingAs($advisor)
        ->withUnencryptedCookie(JobBoardLayoutPreference::COOKIE_NAME, JobBoardLayout::Cards->value)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('data-job-board-layout="cards"', false)
        ->assertSee('data-job-board-layout-explicit="1"', false);
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
        ->and($xpath->query('.//*[@data-job-board-list-body]//tr[@data-job-board-row]', $list)->length)->toBe(2)
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

    expect($xpath->query('//*[@data-job-board-sort="customer"]/ancestor::th[@aria-sort="ascending"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-sort="customer"][@aria-current="true"]')->length)->toBe(1)
        ->and($vehicleXpath->query('//*[@data-job-board-sort="vehicle"]/ancestor::th[@aria-sort="ascending"]')->length)->toBe(1)
        ->and($vehicleXpath->query('//*[@data-job-board-sort="vehicle"][@aria-current="true"]')->length)->toBe(1);
});

test('job board customer vehicle header has a single class attribute', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Header Class');

    $html = $this->actingAs($advisor)->get(route('operations.index'))->assertOk()->getContent();

    expect(preg_match_all(
        '/<th\b[^>]*\bdata-job-board-col="customer-vehicle"[^>]*>/',
        $html,
        $matches
    ))->toBe(1);

    $th = $matches[0][0] ?? '';
    $classCount = preg_match_all('/\sclass="/', $th);

    expect($th)->toContain('class="ark-job-board-col-customer-vehicle"')
        ->and($classCount)->toBe(1)
        ->and(substr_count($th, ' class='))->toBe(1);
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

test('cards view keeps Watch without yellow edge while attention and critical keep accents', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Card Watch Tone');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['view' => 'cards']))
        ->assertOk()
        ->getContent();
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    $watchCards = $xpath->query('//*[@data-job-board-card][@data-workboard-attention="watch"]');
    $attentionCards = $xpath->query('//*[@data-job-board-card][@data-workboard-attention="attention"]');
    $criticalCards = $xpath->query('//*[@data-job-board-card][@data-workboard-attention="critical"]');

    for ($i = 0; $i < $watchCards->length; $i++) {
        $card = $watchCards->item($i);
        expect($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " card-status-start ")]', $card)->length)->toBe(0)
            ->and($xpath->query('.//*[@data-attention-tone="watch"][contains(@class,"text-secondary")]', $card)->length)->toBe(1);
    }

    for ($i = 0; $i < $attentionCards->length; $i++) {
        $card = $attentionCards->item($i);
        expect($xpath->query('.//*[contains(@class,"card-status-start") and contains(@class,"bg-yellow")]', $card)->length)->toBe(1);
    }

    for ($i = 0; $i < $criticalCards->length; $i++) {
        $card = $criticalCards->item($i);
        expect($xpath->query('.//*[contains(@class,"card-status-start") and contains(@class,"bg-red")]', $card)->length)->toBe(1);
    }

    expect($watchCards->length + $attentionCards->length + $criticalCards->length)->toBeGreaterThan(0);
});

test('cards view uses hierarchical Tabler bands and shared card grid', function () {
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
        ->and($xpath->query('//*[@data-job-board-cards-grid][contains(@class,"ark-card-grid")]')->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-ro]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-card-total]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-customer-vehicle]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-concern]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-status]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-technician]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-row-attention]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-card-activity]', $card)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-card-clock]', $card)->length)->toBe(1)
        ->and($card->getAttribute('class'))->toContain('ark-job-board-card')
        ->and($card->getAttribute('class'))->not->toContain('card-sm');
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

    expect($row)->toBeInstanceOf(DOMElement::class);

    $roHref = (string) $xpath->query('.//*[@data-job-board-row-ro]', $row)->item(0)?->attributes?->getNamedItem('href')?->nodeValue;
    $vehicleHref = (string) $xpath->query('.//*[@data-job-board-row-vehicle]', $row)->item(0)?->attributes?->getNamedItem('href')?->nodeValue;
    $customerHref = (string) $xpath->query('.//*[@data-job-board-row-customer]', $row)->item(0)?->attributes?->getNamedItem('href')?->nodeValue;

    expect($roHref)->toContain('/repair-orders/'.$roId)
        ->and($vehicleHref)->toContain('/repair-orders/'.$roId)
        ->and($customerHref)->toContain('/customers/'.$customerId)
        ->and($customerHref)->not->toContain('/repair-orders/')
        ->and($xpath->query('.//*[@data-job-board-row-customer-vehicle]', $row)->length)->toBe(1)
        ->and($xpath->query('.//td[@data-job-board-row-concern]//a', $row)->length)->toBe(0)
        ->and($xpath->query('.//*[@data-job-board-row-promised]', $row)->length)->toBe(0)
        ->and($xpath->query('.//*[@data-job-board-row-parts]', $row)->length)->toBe(0);
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
        'disposition' => \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::Recommended,
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
        ->and($xpath->query('//*[@data-job-board-sort="tech"]/ancestor::th[@aria-sort="ascending"]')->length)->toBe(1)
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
    $rows = $xpath->query('//*[@data-job-board-list-body]//tr[@data-job-board-row]');

    expect($rows->length)->toBeGreaterThanOrEqual(2);
    $firstId = (string) $rows->item(0)?->attributes?->getNamedItem('data-ro-id')?->nodeValue;
    $secondId = (string) $rows->item(1)?->attributes?->getNamedItem('data-ro-id')?->nodeValue;

    expect($firstId)->toBe((string) $high->repair_order_id)
        ->and($secondId)->toBe((string) $low->repair_order_id);
});
