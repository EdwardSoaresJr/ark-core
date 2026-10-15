<?php

use App\Ark\Console\HostedShopSchedule;
use App\Ark\Console\ShopSchedule;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

test('a standalone shop schedule still includes that shop\'s tasks', function (): void {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('shop-excellence:owner-digest')
        ->expectsOutputToContain('ark:platform-heartbeat')
        ->assertSuccessful();

    expect(ShopSchedule::dueArtisanCommands())->toContain('comms:escalate-unhandled');
});

test('a hosted schedule binding does not open the default database', function (): void {
    config([
        'database.connections.mysql.host' => '127.0.0.1',
        'database.connections.mysql.port' => '1',
        'database.connections.mysql.database' => 'unused',
    ]);
    DB::purge('mysql');

    $state = new stdClass;
    $state->used = false;
    app()->instance(HostedShopSchedule::class, new class($state) implements HostedShopSchedule
    {
        public function __construct(private stdClass $state) {}

        public function register(Schedule $schedule): void
        {
            $this->state->used = true;
            $schedule->call(static function (): void {})->everyMinute()->name('hosted-shop-schedule');
        }
    });

    $this->artisan('schedule:list')
        ->expectsOutputToContain('hosted-shop-schedule')
        ->doesntExpectOutputToContain('shop-excellence:owner-digest')
        ->assertSuccessful();

    expect($state->used)->toBeTrue()
        ->and(DB::getDefaultConnection())->toBe('sqlite');
});
