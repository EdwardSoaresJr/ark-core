<?php

use App\Ark\Operations\Workboard\Eval\JobBoardDataTablesPrototypeGate;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

it('exposes the DataTables prototype page in local/testing', function () {
    expect(JobBoardDataTablesPrototypeGate::enabled())->toBeTrue();

    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->get(route('operations.eval.job-board-datatables'))
        ->assertOk()
        ->assertSee('Evaluation prototype', false)
        ->assertSee('ark-job-board-dt', false);
});

it('returns DataTables JSON from the shared Job Board builder', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->getJson(route('operations.eval.job-board-datatables.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'order' => [
                ['column' => 8, 'dir' => 'desc'],
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('draw', 1)
        ->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data',
            'meta' => ['lane', 'queue_total', 'filtered_total', 'sort', 'dir'],
        ]);
});
