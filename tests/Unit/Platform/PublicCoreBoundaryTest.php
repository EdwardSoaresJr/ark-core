<?php

use App\Ark\Platform\Cloud\CloudUrls;

test('an unconfigured stock install does not call Hosted ARK or embed a recovery key', function () {
    $names = [
        'ARK_PLATFORM_BASE_URL',
        'ARK_CLOUD_BASE_URL',
        'ARK_MAIL_SERVICE_URL',
        'ARK_PLATFORM_OFFLINE_RECOVERY_PUBLIC_KEY',
        'ARK_CLOUD_OFFLINE_RECOVERY_PUBLIC_KEY',
    ];

    $saved = [];
    foreach ($names as $name) {
        $saved[$name] = [
            'env' => $_ENV[$name] ?? null,
            'server' => $_SERVER[$name] ?? null,
            'getenv' => getenv($name),
        ];
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }

    try {
        $services = require config_path('services.php');

        expect((string) ($services['ark_platform']['base_url'] ?? ''))->toBe('')
            ->and((string) ($services['ark_cloud']['base_url'] ?? ''))->toBe('')
            ->and((string) ($services['ark_mail']['base_url'] ?? ''))->toBe('')
            ->and((string) ($services['ark_platform']['offline_recovery_public_key'] ?? ''))->toBe('')
            ->and((string) ($services['ark_cloud']['offline_recovery_public_key'] ?? ''))->toBe('');

        config([
            'services.ark_platform.base_url' => $services['ark_platform']['base_url'] ?? '',
            'services.ark_cloud.base_url' => $services['ark_cloud']['base_url'] ?? '',
            'services.ark_mail.base_url' => $services['ark_mail']['base_url'] ?? '',
        ]);

        expect(CloudUrls::base())->toBe('');
    } finally {
        foreach ($saved as $name => $prior) {
            if ($prior['getenv'] !== false && $prior['getenv'] !== null) {
                putenv($name.'='.$prior['getenv']);
            }
            if ($prior['env'] !== null) {
                $_ENV[$name] = $prior['env'];
            }
            if ($prior['server'] !== null) {
                $_SERVER[$name] = $prior['server'];
            }
        }
    }
});
