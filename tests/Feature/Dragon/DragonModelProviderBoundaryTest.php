<?php


use Ark\Saas\Dragon\Agent\Contracts\DragonModelProvider;
use Ark\Saas\Dragon\Agent\DragonProviderUnavailable;
use Ark\Saas\Dragon\Agent\Providers\FakeDragonProvider;
use Ark\Saas\Dragon\Agent\Providers\NotConfiguredDragonProvider;

beforeEach(function (): void {
    if (! bootHostedDragon()) {
        test()->markTestSkipped('Dragon is owned by ARK Shop.');
    }
    migrateHostedDragon();
});

test('default dragon model provider is not configured', function (): void {
    config(['dragon.provider' => 'none']);

    $provider = app(DragonModelProvider::class);

    expect($provider)->toBeInstanceOf(NotConfiguredDragonProvider::class)
        ->and($provider->providerName())->toBe('none')
        ->and($provider->modelName())->toBe('none')
        ->and($provider->health()['ok'])->toBeFalse();

    try {
        $provider->complete([['role' => 'user', 'content' => 'Hello']]);
        expect(false)->toBeTrue('Expected DragonProviderUnavailable');
    } catch (DragonProviderUnavailable $e) {
        expect($e->getMessage())->toBe('Dragon model provider is not configured.');
    }

    try {
        $provider->structured([['role' => 'user', 'content' => '{}']], ['type' => 'object']);
        expect(false)->toBeTrue('Expected DragonProviderUnavailable');
    } catch (DragonProviderUnavailable $e) {
        expect($e->getMessage())->toBe('Dragon model provider is not configured.');
    }
});

test('fake dragon provider works when bound', function (): void {
    config(['dragon.provider' => 'fake']);

    $provider = app(DragonModelProvider::class);

    expect($provider)->toBeInstanceOf(FakeDragonProvider::class)
        ->and($provider->health()['ok'])->toBeTrue();

    $turn = $provider->complete([['role' => 'user', 'content' => 'How is the shop?']]);

    expect($turn->content)->not->toBe('');
});
