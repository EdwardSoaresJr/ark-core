<?php

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
