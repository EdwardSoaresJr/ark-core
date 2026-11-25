<?php

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Workboard\JobBoardLaneCatalogDefaults;
use App\Ark\Operations\Workboard\JobBoardLayout;
use App\Ark\Operations\Workboard\JobBoardListPage;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

/**
 * @return list<string>
 */
function jobBoardFocusLaneKeys(): array
{
    return JobBoardLaneCatalogDefaults::keys();
}

test('every configured lane can be focused with title and count', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $statusByLane = [
        JobBoardLaneCatalogDefaults::ESTIMATES => RepairOrderStatus::Estimate,
        JobBoardLaneCatalogDefaults::WAITING_APPROVAL => RepairOrderStatus::WaitingApproval,
        JobBoardLaneCatalogDefaults::PARTS => RepairOrderStatus::WaitingParts,
        JobBoardLaneCatalogDefaults::WORK_IN_PROGRESS => RepairOrderStatus::InProgress,
        JobBoardLaneCatalogDefaults::COMPLETED => RepairOrderStatus::ReadyPickup,
    ];

    $orders = [];
    foreach ($statusByLane as $laneKey => $status) {
        $orders[$laneKey] = repairOrderForCommunication($status, 'Focus '.$laneKey);
    }

    foreach (jobBoardFocusLaneKeys() as $laneKey) {
        $html = $this->actingAs($advisor)
            ->get(route('operations.index', ['focus' => $laneKey]))
            ->assertOk()
            ->getContent();

        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        $xpath = new DOMXPath($document);
        $focus = $xpath->query('//*[@data-job-board-focus]')->item(0);
        $board = $xpath->query('//*[@data-job-board]')->item(0);

        expect($focus)->toBeInstanceOf(DOMElement::class)
            ->and($focus->getAttribute('data-job-board-focus-lane'))->toBe($laneKey)
            ->and($xpath->query('.//*[@data-job-board-kanban]', $board)->length)->toBe(0)
            ->and(trim((string) $xpath->query('.//*[@data-job-board-focus-count]', $focus)->item(0)?->textContent))->toBe('1')
            ->and($xpath->query('.//*[@data-job-board-focus-back]', $focus)->length)->toBe(1)
            ->and($xpath->query('.//*[@id="ops-card-ro-'.$orders[$laneKey]->repair_order_id.'"]', $focus)->length)->toBe(1);

        foreach ($orders as $otherKey => $otherOrder) {
            if ($otherKey === $laneKey) {
                continue;
            }
            expect($xpath->query('.//*[@id="ops-card-ro-'.$otherOrder->repair_order_id.'"]', $focus)->length)->toBe(0);
        }
    }
});

test('kanban column headers link into focus without mutating preferences', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $advisor->forceFill(['job_board_view' => JobBoardLayout::Cards->value])->save();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Header Focus');

    $boardHtml = $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($boardHtml);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $link = $xpath->query('//*[@data-job-board-kanban-focus="waiting_approval"]')->item(0);

    expect($link)->toBeInstanceOf(DOMElement::class)
        ->and((string) $link->getAttribute('href'))->toContain('focus=waiting_approval')
        ->and($link->getAttribute('aria-label'))->toContain('Focus Waiting Approval');

    $this->actingAs($advisor)
        ->get(route('operations.index', ['focus' => 'waiting_approval']))
        ->assertOk();

    expect($advisor->fresh()->job_board_view)->toBe(JobBoardLayout::Cards->value);
});

test('focus search and technician filters stay inside the focused lane', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $tech = User::factory()->create(['name' => 'Focus Tech']);
    $match = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Focus Match Customer');
    $match->update([
        'concern_summary' => 'FOCUS-SEARCH-TOKEN',
        'assigned_technician_id' => $tech->id,
    ]);
    $otherLane = repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Focus Match Customer');
    $otherLane->update([
        'concern_summary' => 'FOCUS-SEARCH-TOKEN',
        'assigned_technician_id' => $tech->id,
    ]);
    $sameLaneOtherTech = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Other Tech Customer');
    $sameLaneOtherTech->update(['concern_summary' => 'FOCUS-SEARCH-TOKEN']);

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', [
            'focus' => 'waiting_approval',
            'q' => 'FOCUS-SEARCH-TOKEN',
            'tech' => (string) $tech->id,
        ]))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $focus = $xpath->query('//*[@data-job-board-focus]')->item(0);

    expect($focus)->toBeInstanceOf(DOMElement::class)
        ->and(trim((string) $xpath->query('.//*[@data-job-board-focus-count]', $focus)->item(0)?->textContent))->toBe('1')
        ->and($xpath->query('.//*[@id="ops-card-ro-'.$match->repair_order_id.'"]', $focus)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-card-ro-'.$otherLane->repair_order_id.'"]', $focus)->length)->toBe(0)
        ->and($xpath->query('.//*[@id="ops-card-ro-'.$sameLaneOtherTech->repair_order_id.'"]', $focus)->length)->toBe(0)
        ->and($xpath->query('//*[@data-job-board-filters]//input[@name="focus"][@value="waiting_approval"]')->length)->toBe(1);
});

test('invalid focus key safely falls back to the full board', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Invalid Focus');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', ['focus' => 'not_a_real_lane']))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-job-board-focus]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-job-board-kanban]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-job-board-kanban-column]')->length)->toBeGreaterThanOrEqual(5);
});

test('back to board clears focus while preserving search and technician filters', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $tech = User::factory()->create(['name' => 'Board Back Tech']);
    repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'Back Board');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', [
            'focus' => 'waiting_approval',
            'q' => 'Back Board',
            'tech' => (string) $tech->id,
        ]))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $back = $xpath->query('//*[@data-job-board-focus-back]')->item(0);

    expect($back)->toBeInstanceOf(DOMElement::class);
    $href = (string) $back->getAttribute('href');
    expect($href)->toContain('q=Back')
        ->and($href)->toContain('tech='.$tech->id)
        ->and($href)->not->toContain('focus=');

    $boardHtml = $this->actingAs($advisor)->get($href)->assertOk()->getContent();
    $boardDoc = new DOMDocument;
    libxml_use_internal_errors(true);
    $boardDoc->loadHTML($boardHtml);
    libxml_clear_errors();
    $boardXpath = new DOMXPath($boardDoc);

    expect($boardXpath->query('//*[@data-job-board-focus]')->length)->toBe(0)
        ->and($boardXpath->query('//*[@data-job-board-kanban]')->length)->toBe(1)
        ->and($boardXpath->query('//*[@data-job-board-filters]//input[@name="q"][@value="Back Board"]')->length)->toBe(1);
});

test('list view keeps lane tabs while focused cards state remains independent of preference', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $advisor->forceFill(['job_board_view' => JobBoardLayout::List->value])->save();
    $waiting = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'List Focus');
    repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'List Parts');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', [
            'focus' => 'waiting_approval',
            'lane' => 'waiting_approval',
        ]))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $board = $xpath->query('//*[@data-job-board]')->item(0);

    expect($board?->getAttribute('data-job-board-layout'))->toBe('list')
        ->and($xpath->query('.//*[@data-job-board-lane-tab="waiting_approval"][@aria-current="page"]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-job-board-focus]', $board)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="ops-list-ro-'.$waiting->repair_order_id.'"]', $board)->length)->toBe(1)
        ->and($advisor->fresh()->job_board_view)->toBe(JobBoardLayout::List->value);
});

test('zero-result focused lane shows an empty state', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    repairOrderForCommunication(RepairOrderStatus::WaitingParts, 'Empty Focus Parts');

    $html = $this->actingAs($advisor)
        ->get(route('operations.index', [
            'focus' => 'waiting_approval',
            'q' => 'NO-MATCH-TOKEN-XYZ',
        ]))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $focus = $xpath->query('//*[@data-job-board-focus]')->item(0);

    expect($focus)->toBeInstanceOf(DOMElement::class)
        ->and(trim((string) $xpath->query('.//*[@data-job-board-focus-count]', $focus)->item(0)?->textContent))->toBe('0')
        ->and($xpath->query('.//*[@data-job-board-focus-grid]', $focus)->length)->toBe(0)
        ->and((string) $focus->textContent)->toContain('No repair orders match');
});

test('focus load more expands beyond the first batch without duplicating cards', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $total = JobBoardListPage::FOCUS_BATCH_SIZE + 5;
    $ids = [];

    for ($i = 0; $i < $total; $i++) {
        $order = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Load More '.$i);
        $order->update(['updated_at' => now()->subMinutes($total - $i)]);
        $ids[] = $order->repair_order_id;
    }

    $firstHtml = $this->actingAs($advisor)
        ->get(route('operations.index', ['focus' => 'work_in_progress']))
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($firstHtml);
    libxml_clear_errors();
    $xpath = new DOMXPath($document);
    $focus = $xpath->query('//*[@data-job-board-focus]')->item(0);
    $loadMore = $xpath->query('.//*[@data-job-board-focus-load-more]', $focus)->item(0);

    expect($focus)->toBeInstanceOf(DOMElement::class)
        ->and(trim((string) $xpath->query('.//*[@data-job-board-focus-count]', $focus)->item(0)?->textContent))->toBe((string) $total)
        ->and($xpath->query('.//*[@data-job-board-card]', $focus)->length)->toBe(JobBoardListPage::FOCUS_BATCH_SIZE)
        ->and($loadMore)->toBeInstanceOf(DOMElement::class)
        ->and((string) $loadMore->getAttribute('href'))->toContain('focus_limit=');

    $moreHtml = $this->actingAs($advisor)->get((string) $loadMore->getAttribute('href'))->assertOk()->getContent();
    $moreDoc = new DOMDocument;
    libxml_use_internal_errors(true);
    $moreDoc->loadHTML($moreHtml);
    libxml_clear_errors();
    $moreXpath = new DOMXPath($moreDoc);
    $moreFocus = $moreXpath->query('//*[@data-job-board-focus]')->item(0);
    $cardIds = [];
    foreach ($moreXpath->query('.//*[@data-job-board-card]', $moreFocus) as $card) {
        $cardIds[] = $card->getAttribute('id');
    }

    expect(count($cardIds))->toBe($total)
        ->and(count($cardIds))->toBe(count(array_unique($cardIds)))
        ->and($moreXpath->query('.//*[@data-job-board-focus-load-more]', $moreFocus)->length)->toBe(0);

    foreach ($ids as $roId) {
        expect($moreXpath->query('.//*[@id="ops-card-ro-'.$roId.'"]', $moreFocus)->length)->toBe(1);
    }
});

test('focus does not mutate repair orders and stays tenant-scoped', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $order = repairOrderForCommunication(RepairOrderStatus::WaitingApproval, 'No Mutate');
    $statusBefore = (string) ($order->fresh()->status?->value ?? $order->fresh()->status);
    $updatedBefore = $order->fresh()->updated_at?->toISOString();

    $this->actingAs($advisor)
        ->get(route('operations.index', ['focus' => 'waiting_approval']))
        ->assertOk();

    $order->refresh();
    expect((string) ($order->status?->value ?? $order->status))->toBe($statusBefore)
        ->and($order->updated_at?->toISOString())->toBe($updatedBefore);

    $outsider = User::factory()->create();
    $this->actingAs($outsider)
        ->get(route('operations.index', ['focus' => 'waiting_approval']))
        ->assertForbidden();
});
