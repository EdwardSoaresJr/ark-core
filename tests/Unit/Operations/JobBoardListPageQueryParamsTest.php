<?php

use App\Ark\Operations\Workboard\JobBoardListPage;
use Illuminate\Support\Collection;

test('job board query params omit view even when page carries an explicit layout', function () {
    $page = new JobBoardListPage(
        repairOrders: new Collection,
        laneTabs: [],
        laneKey: 'waiting_approval',
        query: 'brake',
        techId: '',
        sortKey: 'updated',
        sortDir: 'desc',
        queueTotal: 80,
        filteredTotal: 60,
        page: 2,
        perPage: 50,
        lastPage: 2,
        rows: [],
        pageColumns: [],
        pageRepairOrders: new Collection,
        view: 'cards',
    );

    expect($page->queryParams())->toBe([
        'lane' => 'waiting_approval',
        'q' => 'brake',
        'page' => 2,
    ])
        ->and($page->queryParams())->not->toHaveKey('view')
        ->and($page->sortUrl('ro'))->not->toContain('view=');
});

test('job board query params carry focus navigation without treating it as a preference', function () {
    $page = new JobBoardListPage(
        repairOrders: new Collection,
        laneTabs: [],
        laneKey: 'all',
        query: 'pad',
        techId: '9',
        sortKey: 'updated',
        sortDir: 'desc',
        queueTotal: 10,
        filteredTotal: 4,
        page: 1,
        perPage: 50,
        lastPage: 1,
        rows: [],
        pageColumns: [],
        pageRepairOrders: new Collection,
        view: 'cards',
        focusKey: 'waiting_approval',
        focusLimit: 80,
    );

    expect($page->queryParams())->toBe([
        'q' => 'pad',
        'tech' => '9',
        'focus' => 'waiting_approval',
        'focus_limit' => 80,
    ])
        ->and($page->boardUrl())->not->toContain('focus=')
        ->and($page->boardUrl())->toContain('q=pad')
        ->and($page->boardUrl())->toContain('tech=9')
        ->and($page->focusUrl('parts'))->toContain('focus=parts')
        ->and($page->focusUrl('parts'))->not->toContain('focus_limit=');
});
