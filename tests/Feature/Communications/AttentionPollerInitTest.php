<?php

use App\Ark\Operations\Workstations\WorkstationPresence;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
});

test('attention pollers are not started twice', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $queue = view('components.operations.call-queue', ['pollerOnly' => true])->render();
    $interrupt = view('components.operations.comms-interrupt-panel')->render();

    expect(substr_count($queue, 'x-data="arkCallQueue()"'))->toBe(1)
        ->and($queue)->not->toContain('x-init=')
        ->and(substr_count($interrupt, 'x-data="arkCommsInterrupt()"'))->toBe(1)
        ->and($interrupt)->not->toContain('x-init=');

    $callQueue = file_get_contents(base_path('resources/js/ark-call-queue.js'));
    $commsInterrupt = file_get_contents(base_path('resources/js/ark-comms-interrupt.js'));

    expect($callQueue)->toContain('if (this.pollTimer !== null)')
        ->and($callQueue)->toContain("url.searchParams.set('summary', '1')")
        ->and($callQueue)->toContain('if (! this.open)')
        ->and($commsInterrupt)->toContain('if (this.pollTimer !== null)');
});
