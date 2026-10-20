<?php

use App\Ark\Operations\RepairOrders\RepairOrderReadCache;
use App\Ark\Operations\Settings\ShopSettingsSchema;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\DB;

function queueJobDouble(): Job
{
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn([]);
    $job->shouldReceive('getJobId')->andReturn('job-1');
    $job->shouldIgnoreMissing();

    return $job;
}

test('a queue worker does not reuse repair-order reads from the previous job', function () {
    $cache = app(RepairOrderReadCache::class);
    $cache->remember('issued-invoice:1', fn () => 'previous-job');

    event(new JobProcessing('redis', queueJobDouble()));

    $seen = $cache->remember('issued-invoice:1', fn () => 'this-job');

    expect($seen)->toBe('this-job');
});

test('a finished or failed queue job drops repair-order reads', function (string $event) {
    $cache = app(RepairOrderReadCache::class);
    $cache->remember('active-ledger:1', fn () => 'held');

    $job = queueJobDouble();
    event($event === JobExceptionOccurred::class
        ? new JobExceptionOccurred('redis', $job, new RuntimeException('failed'))
        : new JobProcessed('redis', $job));

    $seen = $cache->remember('active-ledger:1', fn () => 'reread');

    expect($seen)->toBe('reread');
})->with([
    JobProcessed::class,
    JobExceptionOccurred::class,
]);

test('repair-order reads do not cross databases in one worker', function () {
    $connection = DB::connection();
    $original = $connection->getDatabaseName();
    $cache = app(RepairOrderReadCache::class);

    try {
        $cache->remember('phone-sms:555', fn () => 'shop-a');
        $connection->setDatabaseName('other-shop');

        $seen = $cache->remember('phone-sms:555', fn () => 'shop-b');

        expect($seen)->toBe('shop-b');
    } finally {
        $connection->setDatabaseName($original);
    }
});

test('shop settings columns are listed again for another database', function () {
    $connection = DB::connection();
    $original = $connection->getDatabaseName();

    try {
        ShopSettingsSchema::flush();
        expect(ShopSettingsSchema::has('id'))->toBeTrue();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        ShopSettingsSchema::has('id');
        expect($queries)->toBe(0);

        $connection->setDatabaseName('other-shop');
        ShopSettingsSchema::has('id');

        expect($queries)->toBeGreaterThan(0);
    } finally {
        $connection->setDatabaseName($original);
        ShopSettingsSchema::flush();
    }
});

test('an http request ends by dropping repair-order reads', function () {
    $cache = app(RepairOrderReadCache::class);
    $cache->remember('review-messages:1', fn () => 'open-request');

    $this->app->terminate();

    $seen = app(RepairOrderReadCache::class)->remember('review-messages:1', fn () => 'next-request');

    expect($seen)->toBe('next-request');
});
