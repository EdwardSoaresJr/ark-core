<?php

use App\Ark\Runtime\Broadcast\ReverbDeployment;

beforeEach(function (): void {
    putenv('REVERB_HOST');
    putenv('REVERB_SCHEME');
    putenv('REVERB_PORT');
    unset($_ENV['REVERB_HOST'], $_SERVER['REVERB_HOST']);
    unset($_ENV['REVERB_SCHEME'], $_SERVER['REVERB_SCHEME']);
    unset($_ENV['REVERB_PORT'], $_SERVER['REVERB_PORT']);
});

test('public host derives from app url when reverb host is unset', function (): void {
    config(['app.url' => 'https://app.demo-auto.test']);

    expect(ReverbDeployment::publicHost())->toBe('app.demo-auto.test')
        ->and(ReverbDeployment::publicHostSource())->toBe('APP_URL')
        ->and(ReverbDeployment::scheme())->toBe('https')
        ->and(ReverbDeployment::port())->toBe(443)
        ->and(ReverbDeployment::websocketUrl())->toBe('wss://app.demo-auto.test');
});

test('explicit reverb host overrides app url derivation', function (): void {
    config(['app.url' => 'https://app.demo-auto.test']);
    putenv('REVERB_HOST=ws.example.test');
    $_ENV['REVERB_HOST'] = 'ws.example.test';

    expect(ReverbDeployment::publicHost())->toBe('ws.example.test')
        ->and(ReverbDeployment::publicHostSource())->toBe('REVERB_HOST');
});

test('host mismatch warning appears when reverb host disagrees with app url', function (): void {
    config(['app.url' => 'https://app.demo-auto.test']);
    putenv('REVERB_HOST=autorepairkeeper.com');
    $_ENV['REVERB_HOST'] = 'autorepairkeeper.com';

    expect(ReverbDeployment::hostMismatchWarning())
        ->toContain('autorepairkeeper.com')
        ->toContain('app.demo-auto.test');
});

test('diagnostics expose deployment effective values', function (): void {
    config(['app.url' => 'https://demo.autorepairkeeper.com', 'broadcasting.default' => 'reverb']);
    config(['broadcasting.connections.reverb.key' => 'demo-key']);

    $diagnostics = ReverbDeployment::diagnostics();

    expect($diagnostics['app_url'])->toBe('https://demo.autorepairkeeper.com')
        ->and($diagnostics['reverb_host'])->toBe('demo.autorepairkeeper.com')
        ->and($diagnostics['websocket_url'])->toBe('wss://demo.autorepairkeeper.com')
        ->and($diagnostics['reverb_app_key_configured'])->toBeTrue();
});
