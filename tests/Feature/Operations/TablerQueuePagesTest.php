<?php

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Work\WorkQueue;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

function tablerQueueXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

function tablerQueueHasClass(string $class): string
{
    return 'contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")';
}

test('work queues render on tabler with their count, add form, and lists', function (WorkQueue $queue): void {
    $advisor = actingAsLearnCurrentAdvisor();

    $html = $this->actingAs($advisor)->get(route('operations.work.queue', $queue))->assertOk()->getContent();
    $xpath = tablerQueueXpath($html);
    $page = $xpath->query('//*[@data-work-queue="'.$queue->value.'"]')->item(0);

    expect($html)->toContain('id="ark-sidebar-menu"')
        ->and($xpath->query('//h2['.tablerQueueHasClass('page-title').'][@id="ops-work-queue-'.$queue->value.'"]')->length)->toBe(1)
        ->and($page)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('ancestor-or-self::*['.tablerQueueHasClass('ark-legacy').']', $page)->length)->toBe(0);

    if (in_array($queue, [WorkQueue::Tasks, WorkQueue::FollowUps], true)) {
        $storeRoute = $queue === WorkQueue::Tasks ? route('operations.work.tasks.store') : route('operations.work.follow-ups.store');

        expect($xpath->query('//details[@data-work-item-add]//form[@action="'.$storeRoute.'"]//textarea[@name="notes"]['.tablerQueueHasClass('form-control').']')->length)->toBe(1)
            ->and($xpath->query('//details[@data-work-item-add]//*[@data-work-item-quick-add-cancel]')->length)->toBe(2);
    }
})->with([
    'tasks' => WorkQueue::Tasks,
    'follow-ups' => WorkQueue::FollowUps,
    'scheduled' => WorkQueue::Scheduled,
    'decisions' => WorkQueue::Decisions,
]);

test('each work queue links to the other queues and marks its own', function (WorkQueue $queue): void {
    $advisor = actingAsLearnCurrentAdvisor();

    $html = $this->actingAs($advisor)->get(route('operations.work.queue', $queue))->assertOk()->getContent();
    $xpath = tablerQueueXpath($html);
    $nav = $xpath->query('//nav[@data-work-queue-nav][@aria-label="Work queues"]')->item(0);

    expect($nav)->toBeInstanceOf(DOMElement::class);

    foreach ([WorkQueue::Tasks, WorkQueue::FollowUps, WorkQueue::Scheduled, WorkQueue::Decisions] as $target) {
        $link = $xpath->query('.//a[@href="'.route('operations.work.queue', $target).'"]', $nav)->item(0);

        expect($link)->toBeInstanceOf(DOMElement::class)
            ->and($link->getAttribute('aria-current'))->toBe($target === $queue ? 'page' : '');
    }

    expect($xpath->query('.//a[@href="'.url('/app/work/queues/comms').'"]', $nav)->length)->toBe(0);
})->with([WorkQueue::Tasks, WorkQueue::FollowUps, WorkQueue::Scheduled, WorkQueue::Decisions]);

test('customer decision lanes are tabler cards whose rows keep their actions and quick-add contracts', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Lane',
        lastName: 'Tabler',
        status: RepairOrderStatus::WaitingApproval,
        lineCents: 100_000,
    );
    $shopNumber = $repairOrder->repair_order_id;

    $html = $this->actingAs($advisor)->get(route('operations.work.queue', WorkQueue::Decisions))->assertOk()->getContent();
    $xpath = tablerQueueXpath($html);
    $board = $xpath->query('//*[@data-work-decisions]')->item(0);
    $row = $xpath->query('.//*[@data-decision-row][.//a[@href="'.route('operations.repair-orders.show', $repairOrder).'"]]', $board)->item(0);

    expect($xpath->query('./*/section['.tablerQueueHasClass('card').'][@data-decision-lane]', $board)->length)->toBe(3)
        ->and($xpath->query('.//*['.tablerQueueHasClass('ark-legacy').']', $board)->length)->toBe(0)
        ->and($xpath->query('.//section[@data-decision-lane]//a[@href="'.route('operations.index').'"][normalize-space()="Back to Work"]', $board)->length)->toBe(3)
        ->and($row)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('.//*[@data-decision-row-foot]//*[@data-decision-row-actions]', $row)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-decision-row-foot]/*[@data-decision-row-panel-slot]', $row)->length)->toBe(1);

    foreach (['follow-up' => route('operations.work.follow-ups.store'), 'task' => route('operations.work.tasks.store')] as $kind => $action) {
        $details = $xpath->query('.//*[@data-decision-row-actions]/details[@data-decision-quick-add="'.$kind.'"]', $row)->item(0);

        expect($details)->toBeInstanceOf(DOMElement::class)
            ->and($details->getAttribute('id'))->toBe('ops-decision-quick-add-'.$shopNumber.'-'.$kind)
            ->and($xpath->query('./summary[@data-work-item-quick-add-label]', $details)->length)->toBe(1)
            ->and($xpath->query('./*[@data-decision-quick-add-owner="'.$details->getAttribute('id').'"]//form[@data-work-item-quick-add-form][@action="'.$action.'"]', $details)->length)->toBe(1)
            ->and($xpath->query('.//form//input[@type="hidden"][@name="repair_order_shop_number"][@value="'.$shopNumber.'"]', $details)->length)->toBe(1)
            ->and($xpath->query('.//form//*[@data-quick-add-feedback][@hidden]', $details)->length)->toBe(1)
            ->and($xpath->query('.//form//*[@data-quick-add-error][@hidden]', $details)->length)->toBe(1)
            ->and($xpath->query('.//*[@data-work-item-quick-add-cancel]', $details)->length)->toBe(2);
    }

    $schedule = $xpath->query('.//*[@data-decision-row-actions]/details[@data-decision-quick-add="schedule"]', $row)->item(0);

    expect($schedule)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('./*[@data-decision-quick-add-owner="'.$schedule->getAttribute('id').'"]//form[@data-decision-schedule-form]//input[@type="date"][@name="scheduled_for"][@required]', $schedule)->length)->toBe(1)
        ->and($xpath->query('.//form[@data-work-item-quick-add-form]', $schedule)->length)->toBe(0);
});
