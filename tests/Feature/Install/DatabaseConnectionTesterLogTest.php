<?php

it('does not include the database password in connection-failure logs', function () {
    $src = (string) file_get_contents(app_path('Ark/Install/DatabaseConnectionTester.php'));

    expect($src)->toContain("Log::warning('installer.database.test_failed'")
        ->and($src)->not->toContain("'password' =>")
        ->and($src)->toContain('password=***');
});
