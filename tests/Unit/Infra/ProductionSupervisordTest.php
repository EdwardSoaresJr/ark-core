<?php

test('production supervisord runs laravel scheduler', function () {
    $config = file_get_contents(base_path('infra/coolify/supervisord.conf'));

    expect($config)
        ->toContain('[program:scheduler]')
        ->toContain('artisan schedule:work');
});
