<?php

use Illuminate\Support\Facades\Route;

test('stock core boots without dragon providers or routes', function () {
    if (Route::getFacadeApplication() === null) {
        $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    }

    $providers = file_get_contents(base_path('bootstrap/providers.php'));

    expect($providers)->not->toContain('Dragon');
    expect(is_dir(app_path('Ark/Dragon')))->toBeFalse();
    expect(is_file(config_path('dragon.php')))->toBeFalse();
    expect(app()->getProvider('Ark\\Saas\\Dragon\\Agent\\DragonAgentServiceProvider'))->toBeNull();
    expect(Route::has('api.desk.dragon.chat'))->toBeFalse();
    expect(Route::has('api.dragon-agent.chat'))->toBeFalse();
    expect(Route::has('api.station.dragon.chat'))->toBeFalse();
    expect(Route::has('api.station.attention-nudge'))->toBeFalse();
    expect(Route::has('api.tech.dragon.rewrite'))->toBeFalse();
    expect(Route::has('api.station.dashboard'))->toBeTrue();
    expect(Route::has('api.desk.work'))->toBeTrue();
    expect(Route::has('api.tech.me.work'))->toBeTrue();
});
