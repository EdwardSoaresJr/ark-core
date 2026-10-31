<?php

use App\Ark\Runtime\Database\SchemaPresence;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

test('a test can cache a table it created as present', function (): void {
    Schema::create('schema_presence_isolation_probe', fn (Blueprint $table) => $table->id());

    expect(SchemaPresence::hasTable('schema_presence_isolation_probe'))->toBeTrue();
});

test('the next test does not inherit that cached table', function (): void {
    expect(Schema::hasTable('schema_presence_isolation_probe'))->toBeFalse()
        ->and(SchemaPresence::hasTable('schema_presence_isolation_probe'))->toBeFalse();
});
