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

test('reverb deployment health endpoint exposes effective configuration', function (): void {
    config(['app.url' => 'https://app.demo-auto.test', 'broadcasting.default' => 'reverb']);
    config(['broadcasting.connections.reverb.key' => 'health-test-key']);

    $this->get('/up/reverb')
        ->assertOk()
        ->assertJsonPath('app_url', 'https://app.demo-auto.test')
        ->assertJsonPath('reverb_host', 'app.demo-auto.test')
        ->assertJsonPath('reverb_host_source', 'APP_URL')
        ->assertJsonPath('websocket_url', 'wss://app.demo-auto.test')
        ->assertJsonPath('reverb_app_key_configured', true);
});

test('reverb deployment health reports host mismatch warning', function (): void {
    config(['app.url' => 'https://app.demo-auto.test']);
    putenv('REVERB_HOST=autorepairkeeper.com');
    $_ENV['REVERB_HOST'] = 'autorepairkeeper.com';

    $this->get('/up/reverb')
        ->assertOk()
        ->assertJsonPath('warning', ReverbDeployment::hostMismatchWarning());
});
