<?php

use App\Ark\Install\InstallFinalizeRunner;

it('exposes a synchronous finalize path for constrained hosts', function (): void {
    expect(method_exists(InstallFinalizeRunner::class, 'runSynchronously'))->toBeTrue();
});
