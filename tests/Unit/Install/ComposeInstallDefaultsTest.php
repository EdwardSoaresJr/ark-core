<?php

it('does not ship shared mysql or reverb secrets in compose defaults', function () {
    $compose = (string) file_get_contents(base_path('docker-compose.yml'));
    $example = (string) file_get_contents(base_path('.env.example'));

    expect($compose)->not->toContain('MYSQL_PASSWORD: ark')
        ->and($compose)->not->toContain('MYSQL_PASSWORD=ark')
        ->and($compose)->not->toContain('MYSQL_ROOT_PASSWORD: arkroot')
        ->and($compose)->not->toContain('DB_PASSWORD: ark')
        ->and($compose)->not->toContain('arklocalreverbkey20c')
        ->and($compose)->not->toContain('arklocalreverbsecret20charsx')
        ->and($compose)->toContain('ARK_MANAGED_DATABASE: "true"')
        ->and($example)->not->toMatch('/^DB_PASSWORD=ark$/m')
        ->and($example)->not->toContain('arkroot');
});

it('does not generate secrets inside the browser setup after mysql has started', function () {
    $controller = (string) file_get_contents(app_path('Ark/Install/Http/SetupWizardController.php'));
    $bootstrap = (string) file_get_contents(base_path('docker/selfhost/bootstrap-install-secrets.sh'));

    expect($controller)->not->toContain('MYSQL_ROOT_PASSWORD')
        ->and($controller)->not->toContain('random_bytes')
        ->and($bootstrap)->toContain('ARK_INSTALL_SECRETS_FILE')
        ->and($bootstrap)->toContain('DB_PASSWORD');
});

it('keeps mysql unpublished on the vultr public vps overlay', function () {
    $vultr = (string) file_get_contents(base_path('docker-compose.vultr.yml'));
    $compose = (string) file_get_contents(base_path('docker-compose.yml'));

    expect($compose)->toContain('"3307:3306"')
        ->and($vultr)->toContain('ports: !override []')
        ->and($vultr)->toContain('Public VPS: MySQL stays on the Compose network only');
});
