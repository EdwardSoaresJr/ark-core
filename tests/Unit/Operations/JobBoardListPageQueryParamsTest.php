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
