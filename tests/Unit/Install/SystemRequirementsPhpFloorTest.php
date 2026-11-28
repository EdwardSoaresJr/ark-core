<?php

use App\Ark\Install\SystemRequirementsChecker;

it('requires PHP 8.4.1 or newer before installation can proceed', function () {
    $checks = app(SystemRequirementsChecker::class)->check();
    $php = collect($checks)->firstWhere('id', 'php_version');

    expect($php)->not->toBeNull()
        ->and($php['label'])->toBe('PHP version')
        ->and($php['detail'])->toContain('8.4.1');

    if (version_compare(PHP_VERSION, '8.4.1', '>=')) {
        expect($php['status'])->toBe('pass');
    } else {
        expect($php['status'])->toBe('fail');
    }

    $required = collect($checks)
        ->whereIn('id', ['ext_gd', 'ext_zip', 'ext_pdo_mysql'])
        ->pluck('id')
        ->all();

    expect($required)->toContain('ext_gd')
        ->and($required)->toContain('ext_zip')
        ->and($required)->toContain('ext_pdo_mysql');
});
