<?php

test('bookstack arkademy theme ships ark ecosystem favicons', function () {
    $dir = dirname(__DIR__, 3).'/infra/coolify/bookstack/themes/arkademy/public/favicon';

    expect(is_file("{$dir}/favicon.ico"))->toBeTrue()
        ->and(is_file("{$dir}/ark-16x16.png"))->toBeTrue()
        ->and(is_file("{$dir}/ark-32x32.png"))->toBeTrue()
        ->and(is_file("{$dir}/ark-180x180.png"))->toBeTrue();
});
