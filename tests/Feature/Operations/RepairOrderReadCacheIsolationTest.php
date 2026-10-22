<?php

use App\Ark\Operations\RepairOrders\RepairOrderReadCache;
use App\Ark\Operations\Settings\ShopSettingsSchema;
use App\Ark\Runtime\DatabaseReadScope;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

test('the read scope names the connection and the database', function () {
    $connection = DB::connection();
    $original = $connection->getDatabaseName();
    $key = DatabaseReadScope::key();

    $connectionName = $connection->getName();

    expect($key)->toContain($connectionName)
        ->and($key)->toContain($connection->getDriverName())
        ->and($key)->toContain($original);

    try {
        $connection->setDatabaseName('other-shop-database');

        expect(DatabaseReadScope::key())->not->toBe($key)
            ->and(DatabaseReadScope::key())->toContain('other-shop-database')
            ->and($connection->getName())->toBe($connectionName);
    } finally {
        $connection->setDatabaseName($original);
    }
});

test('shop settings columns survive a request boundary until migrations change', function () {
    ShopSettingsSchema::flush();
    expect(ShopSettingsSchema::has('id'))->toBeTrue();

    $listings = 0;
    DB::listen(function ($query) use (&$listings): void {
        if (str_contains(strtolower($query->sql), 'pragma_table_xinfo')
            || str_contains(strtolower($query->sql), 'information_schema.columns')) {
            $listings++;
        }
    });

    RepairOrderReadCache::beginUnitOfWork();
    expect(ShopSettingsSchema::has('id'))->toBeTrue()
        ->and($listings)->toBe(0);

    $this->app->terminate();
    expect(ShopSettingsSchema::has('id'))->toBeTrue()
        ->and($listings)->toBe(0);
});

test('two databases do not reuse each others shop settings columns', function () {
    $previous = DB::getDefaultConnection();
    $previousConfig = config('database.default');

    $switch = function (string $name): void {
        config(['database.default' => $name]);
        DB::setDefaultConnection($name);
        app()->forgetInstance('db.schema');
    };

    try {
        foreach (['schema_shop_a' => 'only_on_a', 'schema_shop_b' => 'only_on_b'] as $name => $column) {
            config(["database.connections.{$name}" => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]]);
            $switch($name);
            Schema::create('shop_settings', function (Blueprint $table) use ($column): void {
                $table->id();
                $table->string($column);
            });
        }

        ShopSettingsSchema::flush();

        $switch('schema_shop_a');
        expect(ShopSettingsSchema::has('only_on_a'))->toBeTrue()
            ->and(ShopSettingsSchema::has('only_on_b'))->toBeFalse();

        RepairOrderReadCache::beginUnitOfWork();
        $switch('schema_shop_b');
        expect(ShopSettingsSchema::has('only_on_b'))->toBeTrue()
            ->and(ShopSettingsSchema::has('only_on_a'))->toBeFalse();

        RepairOrderReadCache::beginUnitOfWork();
        $switch('schema_shop_a');
        $listings = 0;
        DB::listen(function ($query) use (&$listings): void {
            if (str_contains(strtolower($query->sql), 'pragma_table_xinfo')) {
                $listings++;
            }
        });

        expect(ShopSettingsSchema::has('only_on_a'))->toBeTrue()
            ->and(ShopSettingsSchema::has('only_on_b'))->toBeFalse()
            ->and($listings)->toBe(0);
    } finally {
        $switch($previous);
        config(['database.default' => $previousConfig]);
        ShopSettingsSchema::flush();
    }
});

test('a migration record or a finished migration refreshes shop settings columns', function () {
    $previous = DB::getDefaultConnection();
    $previousConfig = config('database.default');
    config(['database.connections.schema_shop_migrate' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);

    try {
        config(['database.default' => 'schema_shop_migrate']);
        DB::setDefaultConnection('schema_shop_migrate');
        app()->forgetInstance('db.schema');
        Schema::create('migrations', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });
        Schema::create('shop_settings', function (Blueprint $table): void {
            $table->id();
        });
        DB::table('migrations')->insert([
            'migration' => '2026_10_20_120000_create_shop_settings',
            'batch' => 1,
        ]);

        ShopSettingsSchema::flush();
        expect(ShopSettingsSchema::has('added_by_migration'))->toBeFalse();

        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->string('added_by_migration')->nullable();
        });
        RepairOrderReadCache::beginUnitOfWork();
        expect(ShopSettingsSchema::has('added_by_migration'))->toBeFalse();

        DB::table('migrations')->insert([
            'migration' => '2026_10_20_120100_add_shop_settings_column',
            'batch' => 2,
        ]);
        RepairOrderReadCache::beginUnitOfWork();
        expect(ShopSettingsSchema::has('added_by_migration'))->toBeTrue();

        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->string('added_by_event')->nullable();
        });
        event(new MigrationsEnded('up', []));
        expect(ShopSettingsSchema::has('added_by_event'))->toBeTrue();
    } finally {
        config(['database.default' => $previousConfig]);
        DB::setDefaultConnection($previous);
        app()->forgetInstance('db.schema');
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
