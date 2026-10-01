<?php

use App\Ark\Install\InstallFinalizeRunner;

it('resolves a CLI php binary instead of php-fpm', function (): void {
    $binary = InstallFinalizeRunner::phpCliBinary();

    expect($binary)->toBeString()->not->toBe('');
    expect(strtolower(basename($binary)))->not->toContain('fpm');
});
