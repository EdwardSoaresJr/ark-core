<?php

use App\Ark\Runtime\DemoEventLog;
use App\Ark\Runtime\DemoInstall;

beforeEach(function () {
    $this->log = storage_path('framework/testing/demo-events-'.bin2hex(random_bytes(4)).'.jsonl');
    config(['ark.demo_events' => $this->log]);
});

afterEach(function () {
    if (is_file($this->log)) {
        unlink($this->log);
    }
});

test('the public demo gate points at the repository without opening it yet', function () {
    config(['app.url' => 'https://demo.arksms.com']);

    $this->get('/')
        ->assertOk()
        ->assertSee('ARK Public Demo', false)
        ->assertSee('Explore the open-source shop management system.', false)
        ->assertSee('Open the Demo', false)
        ->assertSee('View on GitHub', false)
        ->assertSee('Hosted ARK coming soon', false)
        ->assertSee(route('login'), false)
        ->assertSee(route('demo.github', ['source_path' => '/']), false)
        ->assertSee('to=hosted', false)
        ->assertDontSee('Free and open source', false);

    expect(is_file($this->log))->toBeFalse();
});

test('a shop that is not the public demo keeps its own front door', function () {
    config(['app.url' => 'https://app.lugsnplugs.test']);

    $this->get('/')->assertNotFound();
    $this->get('/demo/github')->assertNotFound();
    $this->get(route('login'))->assertOk()->assertDontSee('ARK Public Demo', false);
});

test('github clicks record the page they came from and leave the demo', function () {
    config(['app.url' => 'https://demo.arksms.com']);

    $this->get('/demo/github?source_path=/app/appointments')
        ->assertRedirect(DemoInstall::REPOSITORY_URL);

    $this->get('/demo/github?source_path=https://evil.example/phish')
        ->assertRedirect(DemoInstall::REPOSITORY_URL);

    $this->get('/demo/github?source_path=/&to=hosted')
        ->assertRedirect(DemoInstall::HOSTED_URL);

    $this->get('/demo/github?source_path=/&to=https://evil.example')
        ->assertRedirect(DemoInstall::REPOSITORY_URL);

    $lines = array_values(array_filter(explode("\n", (string) file_get_contents($this->log))));

    expect($lines)->toHaveCount(4)
        ->and(json_decode($lines[0], true)['event'])->toBe('demo_github_click')
        ->and(json_decode($lines[0], true)['source_path'])->toBe('/app/appointments')
        ->and(json_decode($lines[1], true)['source_path'])->toBe('/')
        ->and(json_decode($lines[2], true)['event'])->toBe('demo_hosted_click')
        ->and(json_decode($lines[2], true)['source_path'])->toBe('/')
        ->and(json_decode($lines[3], true)['event'])->toBe('demo_github_click');
});

test('the sign-in page keeps a thin link back to the source', function () {
    config(['app.url' => 'https://demo.arksms.com']);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('ARK Public Demo', false)
        ->assertSee('Explore the open-source shop management system.', false)
        ->assertSee('Hosted ARK coming soon', false)
        ->assertSee(route('demo.github', ['source_path' => '/app/login']), false)
        ->assertSee('source_path=%2Fapp%2Flogin', false)
        ->assertSee('to=hosted', false);
});

test('recorded paths stay on this site', function () {
    expect(DemoEventLog::sourcePath('/app'))->toBe('/app')
        ->and(DemoEventLog::sourcePath('//github.com'))->toBe('/')
        ->and(DemoEventLog::sourcePath("/app\nSet-Cookie"))->toBe('/');
});
