<?php

use App\Ark\Dragon\Agent\DragonOpenAiFunctionNames;

test('canonical tool names round-trip through OpenAI-safe names', function (): void {
    $canonical = [
        'shop.current_summary',
        'shop.financial_snapshot',
        'repair_orders.search',
        'repair_orders.get',
        'memory.recall',
    ];

    $index = DragonOpenAiFunctionNames::index($canonical);

    expect($index['shop_current_summary'])->toBe('shop.current_summary')
        ->and($index['repair_orders_search'])->toBe('repair_orders.search');

    foreach ($canonical as $name) {
        $provider = DragonOpenAiFunctionNames::toProvider($name);
        expect($provider)->toMatch('/^[a-zA-Z0-9_-]+$/')
            ->and(DragonOpenAiFunctionNames::toCanonical($provider, $canonical))->toBe($name);
    }
});

test('unknown provider tool names fail closed', function (): void {
    expect(fn () => DragonOpenAiFunctionNames::toCanonical('shell_exec', ['shop.current_summary']))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => DragonOpenAiFunctionNames::toCanonical('shop.current_summary', ['shop.current_summary']))
        ->toThrow(InvalidArgumentException::class);
});

test('provider name collisions fail closed', function (): void {
    expect(fn () => DragonOpenAiFunctionNames::index(['shop.current.summary', 'shop.current_summary']))
        ->toThrow(InvalidArgumentException::class);
});
